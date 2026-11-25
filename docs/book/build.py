"""Render a markdown-source book to HTML + PDF.

Pipeline:
  1. Draw diagrams under docs/assets/diagrams/: run every <name>.py there
     (except _*.py helpers) to write <name>.svg, then re-render any
     <name>.drawio to <name>.png
  2. Build mkdocs-material multi-page site into dist/site/ (optional)
  3. Build single-page print book into dist/book.html (WYSIWYG with PDF)
  4. Render dist/book.html to dist/<PDF_FILENAME> via Playwright

The single-page print book is the source of the PDF. Open dist/book.html
in any browser and what you see is what the PDF will look like.

Tools required on the build machine:
  - draw.io desktop CLI (only if the book has .drawio diagrams). Found via
    $DRAWIO_BIN, then `drawio` / `draw.io` on PATH, then
    /Applications/draw.io.app on macOS
  - uv (any recent version)
  - Google Chrome (Playwright uses channel="chrome" to reuse it; no extra
    browser download needed)

Set SKIP_PDF=1 to skip the PDF step (faster iteration on HTML/CSS).
Set SKIP_MKDOCS=1 to skip the multi-page site (faster builds).

Setup once:
  uv sync                       # creates .venv from pyproject.toml + uv.lock

Run:
  uv run python build.py        # builds HTML + PDF into dist/
  uv run --with . build.py      # same (alternate form)

Dependencies are managed via pyproject.toml and uv.lock — no inline PEP 723
script metadata needed. Commit both files for reproducible builds.
"""

from __future__ import annotations

import re
import shutil
from html import escape
import subprocess
import sys
from pathlib import Path

import markdown
from playwright.sync_api import sync_playwright

BOOK_DIR = Path(__file__).resolve().parent

# Where chapter markdowns live. Default "docs" assumes the book sits at the
# repo root and keeps content cleanly separated from build infrastructure
# (build.py, pyproject.toml, print/). Set CONTENT_SUBDIR = "" to put chapters
# directly at the book root — sensible when the book is itself nested under
# another `docs/` directory (e.g. `<repo>/docs/book/`) and `docs/book/docs/`
# would read as a double prefix.
# The guide's single source is the repo's README.md. split_readme() cuts it at
# its "## N. Title" sections into chapter files under dist/chapters/, so the
# PDF never drifts from what clinics read on GitHub.
README = BOOK_DIR.parent.parent / "README.md"
CONTENT_SUBDIR = ""
DIST_DIR = BOOK_DIR / "dist"
DOCS_DIR = DIST_DIR / "chapters"
PRINT_DIR = BOOK_DIR / "print"
DRAWIO_MACOS_APP = Path("/Applications/draw.io.app/Contents/MacOS/draw.io")

# === EDIT THESE FOR YOUR BOOK ===
BOOK_TITLE = "Clinic API Guide"
BOOK_CODENAME = "iws-clinic-api"
BOOK_SUBTITLE = "Serve your medical check-up data to iws through a small, read-only REST API"
BOOK_VERSION = "contract v1 · template 1.0.0"
BOOK_FOOTER = "For clinic developers · GMC UGM · Korpagama"
PDF_FILENAME = "iws-clinic-api-guide.pdf"

# Chapters in nav order: (markdown filename, display title). Filled by split_readme().
CHAPTERS: list[tuple[str, str]] = []


def split_readme() -> None:
    """Cut README.md into chapters: the intro, then one per '## N. Title' section."""
    text = README.read_text(encoding="utf-8")
    # The README's own contents list links to GitHub anchors; the book has its own TOC.
    text = re.sub(r"\nContents:\n\n(?:\d+\. .*\n)+", "\n", text)
    # Repo-relative links don't resolve in a PDF: keep the name as code.
    text = re.sub(r"\[`?([^\]`]+)`?\]\((?!https?://|#)([^)]+)\)", r"`\1`", text)
    text = text.replace("\n---\n", "\n")
    parts = re.split(r"^## (\d+)\. (.+)$", text, flags=re.M)
    intro = re.sub(r"\A# .*\n", "", parts[0]).strip()

    if DOCS_DIR.exists():
        shutil.rmtree(DOCS_DIR)
    DOCS_DIR.mkdir(parents=True)
    CHAPTERS.clear()
    (DOCS_DIR / "00-about.md").write_text(intro + "\n", encoding="utf-8")
    CHAPTERS.append(("00-about.md", "About this guide"))
    for i in range(1, len(parts), 3):
        number, title, body = parts[i], parts[i + 1].strip(), parts[i + 2]
        name = f"{int(number):02d}.md"
        # Sections were "##"; inside a chapter their subsections ("###") become "##".
        body = re.sub(r"^###", "##", body, flags=re.M)
        (DOCS_DIR / name).write_text(body.strip() + "\n", encoding="utf-8")
        CHAPTERS.append((name, title))
# === END EDIT ZONE ===


def fail(msg: str) -> None:
    print(f"ERROR: {msg}", file=sys.stderr)
    sys.exit(1)


def find_drawio() -> str:
    """$DRAWIO_BIN wins, then drawio/draw.io on PATH (Linux), then the macOS app."""
    import os
    override = os.environ.get("DRAWIO_BIN")
    if override:
        if not Path(override).is_file():
            fail(f"DRAWIO_BIN={override} does not exist")
        return override
    for name in ("drawio", "draw.io"):
        found = shutil.which(name)
        if found:
            return found
    if DRAWIO_MACOS_APP.is_file():
        return str(DRAWIO_MACOS_APP)
    fail("draw.io CLI not found: set DRAWIO_BIN, put drawio on PATH, "
         f"or install the macOS app at {DRAWIO_MACOS_APP}")


def diagram_scripts() -> list[Path]:
    """Every <name>.py under assets/diagrams/ is a diagram; _*.py are helpers."""
    d = DOCS_DIR / "assets" / "diagrams"
    return sorted(p for p in d.glob("*.py") if not p.name.startswith("_")) if d.exists() else []


def drawio_files() -> list[Path]:
    d = DOCS_DIR / "assets" / "diagrams"
    return sorted(d.glob("*.drawio")) if d.exists() else []


def render_svg_diagrams() -> None:
    print("==> 1/4  Drawing SVG diagrams...")
    for src in diagram_scripts():
        out = src.with_suffix(".svg")
        print(f"    {src.name} -> {out.name}")
        out.unlink(missing_ok=True)  # an old .svg must not hide a script that saves nothing
        subprocess.run([sys.executable, src.name, out.name], cwd=src.parent, check=True)
        if not out.is_file():
            fail(f"{src.name} ran but did not write {out.name}")


def render_drawio_diagrams() -> None:
    print("==> 1/4  Rendering drawio diagrams...")
    drawio = find_drawio()

    for src in drawio_files():
        out = src.with_suffix(".png")
        print(f"    {src.name} -> {out.name}")
        subprocess.run(
            [drawio, "--export", "-f", "png", "--border", "16",
             "--scale", "2", "-o", str(out), str(src)],
            check=True,
        )


def build_mkdocs() -> None:
    print("==> 2/4  Building mkdocs multi-page site...")
    mkdocs = shutil.which("mkdocs")
    if mkdocs is None:
        fail("mkdocs not on PATH: run `uv sync --extra site` to install the multi-page site deps")
    subprocess.run([mkdocs, "build", "--clean"], cwd=BOOK_DIR, check=True)


def rewrite_chapter_links(html: str, slugs: dict[str, str]) -> str:
    """Links between chapters (`02-restore.md#steps`) point at .md files that
    don't exist in dist/. Point them at the chapter's section or the heading
    inside it instead: `02-restore.md` -> `#ch-02-restore`,
    `02-restore.md#steps` -> `#ch-02-restore--steps`."""
    def _sub(m: re.Match) -> str:
        target, frag = m.group(1), m.group(2)
        if target not in slugs:
            return m.group(0)
        return f'href="#{slugs[target]}--{frag}"' if frag else f'href="#{slugs[target]}"'
    return re.sub(r'href="([^"#:]+\.md)(?:#([^"]*))?"', _sub, html)


def prefix_heading_ids(html: str, slug: str) -> str:
    """`toc` ids are only unique within one chapter; prefix them with the chapter."""
    return re.sub(r'(<h[2-6][^>]* id=")([^"]+)"', lambda m: f'{m.group(1)}{slug}--{m.group(2)}"', html)


FRONT_MATTER = re.compile(r"\A---\n.*?\n---\n", re.S)


def slugify(title: str) -> str:
    return re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')


def convert_obsidian_callouts(text: str) -> str:
    """Convert Obsidian-style callouts to Python-Markdown admonition syntax.

    Obsidian:
        > [!info] Title
        > body line 1
        > body line 2

    Becomes:
        !!! info "Title"
            body line 1
            body line 2

    Title is optional. Maps unknown types to 'note' to keep CSS classes sane.
    """
    callout_re = re.compile(
        r'^(?P<indent> *)> \[!(?P<type>[A-Za-z]+)\][ \t]*(?P<title>.*)\n'
        r'(?P<body>(?:^(?P=indent)> ?.*\n?)*)',
        re.MULTILINE,
    )

    def _strip_blockquote(body: str, indent: str) -> list:
        out_lines = []
        for line in body.splitlines():
            line = line[len(indent):] if line.startswith(indent) else line
            if line.startswith("> "):
                out_lines.append(line[2:])
            elif line.startswith(">"):
                out_lines.append(line[1:].lstrip())
            else:
                out_lines.append(line)
        return out_lines

    def _replace(match: re.Match) -> str:
        indent = match.group('indent') or ''
        ctype = match.group('type').lower()
        title = match.group('title').strip()
        body_lines = _strip_blockquote(match.group('body'), indent)
        header = f'{indent}!!! {ctype}'
        if title:
            header += f' "{title}"'
        indented_body = '\n'.join(f'{indent}    {line}' for line in body_lines)
        return f'{header}\n{indented_body}\n'

    return callout_re.sub(_replace, text)


def build_print_book() -> Path:
    print("==> 3/4  Building single-page print book...")

    template = (PRINT_DIR / "template.html").read_text(encoding="utf-8")

    md = markdown.Markdown(
        extensions=[
            "fenced_code",
            "tables",
            "admonition",
            "attr_list",
            "md_in_html",
            "pymdownx.superfences",
            "pymdownx.highlight",
            "pymdownx.inlinehilite",
            "toc",  # heading ids, so links to `chapter.md#heading` can land
        ],
        extension_configs={
            "pymdownx.highlight": {
                "use_pygments": True,
                "css_class": "highlight",
                "noclasses": False,
            },
        },
    )

    slugs = {f: f"ch-{i:02d}-{slugify(t)}" for i, (f, t) in enumerate(CHAPTERS, 1)}
    chapter_blocks = []
    toc_items = []
    for idx, (filename, title) in enumerate(CHAPTERS, 1):
        md_path = DOCS_DIR / filename
        raw = md_path.read_text(encoding="utf-8")
        # Front matter is for the mkdocs site; the book doesn't render it
        raw = FRONT_MATTER.sub("", raw.lstrip("\ufeff"))
        # Strip the first H1 — we generate our own numbered chapter heading
        lines = raw.lstrip().split("\n")
        if lines and lines[0].startswith("# "):
            lines = lines[1:]
        body_md = "\n".join(lines).lstrip()
        body_md = convert_obsidian_callouts(body_md)

        slug = slugs[filename]
        md.reset()
        body_html = md.convert(body_md)
        body_html = prefix_heading_ids(body_html, slug)
        body_html = rewrite_chapter_links(body_html, slugs)

        safe_title = escape(title)
        chapter_blocks.append(
            f'    <section class="chapter" id="{slug}">\n'
            f'      <h1>{idx}. {safe_title}</h1>\n'
            f'      {body_html}\n'
            f'    </section>\n'
        )
        toc_items.append(
            f'        <li><a href="#{slug}">{safe_title}</a></li>'
        )

    # Pygments CSS for syntax highlighting in code blocks.
    # Use a DARK style ('nord' matches the `pre { background: #2e3440 }` in print.css).
    # A light style like 'default' puts dark-red strings / dark comments on the dark code
    # background — low contrast, unreadable in the PDF. Keep the style and the pre background
    # in the same light/dark family.
    try:
        from pygments.formatters import HtmlFormatter
        pygments_css = HtmlFormatter(style="nord").get_style_defs(".highlight")
    except ImportError:
        pygments_css = ""

    # Plain token replacement, not str.format: braces in the template's own
    # CSS or JS then need no escaping.
    fields = {
        "title": escape(BOOK_TITLE),
        "codename": escape(BOOK_CODENAME),
        "subtitle": escape(BOOK_SUBTITLE),
        "version": escape(BOOK_VERSION),
        "footer": escape(BOOK_FOOTER),
        "toc_items": "\n".join(toc_items),
        "chapters": "".join(chapter_blocks),
    }
    html = template
    for key, value in fields.items():
        html = html.replace("{" + key + "}", value)

    # Inject pygments CSS just before </head>
    if pygments_css:
        html = html.replace(
            "</head>",
            f'<style>\n{pygments_css}\n</style>\n</head>',
        )

    DIST_DIR.mkdir(parents=True, exist_ok=True)
    book_html = DIST_DIR / "book.html"
    book_html.write_text(html, encoding="utf-8")

    # Copy print.css + paged.js polyfill alongside book.html so relative
    # hrefs resolve. paged.js paginates the document into A4 sheets in both
    # browser and Playwright PDF render — true WYSIWYG.
    shutil.copy(PRINT_DIR / "print.css", DIST_DIR / "print.css")
    shutil.copy(PRINT_DIR / "paged.polyfill.js", DIST_DIR / "paged.polyfill.js")

    # Copy assets/ tree (diagrams, screenshots) into dist/ so book.html is
    # self-contained — paths like `assets/diagrams/foo.png` resolve next to it.
    dist_assets = DIST_DIR / "assets"
    if dist_assets.exists():
        shutil.rmtree(dist_assets)
    # Diagram sources stay behind; only the rendered .svg/.png are needed.
    # A text-only book may have no assets/ at all.
    if (DOCS_DIR / "assets").is_dir():
        shutil.copytree(DOCS_DIR / "assets", dist_assets,
                        ignore=shutil.ignore_patterns("*.py", "*.drawio", "__pycache__"))

    print(f"    wrote {book_html} ({book_html.stat().st_size // 1024} KB)")
    return book_html


def render_pdf(book_html: Path) -> Path:
    print("==> 4/4  Rendering PDF via Playwright (channel=chrome)...")

    # paged.js needs http:// to run reliably — Chrome restricts script-driven
    # DOM rewrite under file:// (no errors, just silently nothing happens).
    # Start a tiny HTTP server rooted at dist/ for the duration of the render.
    import contextlib
    import http.server
    import socketserver
    import threading

    class _QuietHandler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *args, **kwargs):
            pass

    @contextlib.contextmanager
    def serve(root: Path, port: int = 0):
        handler = lambda *a, **kw: _QuietHandler(*a, directory=str(root), **kw)
        with socketserver.TCPServer(("127.0.0.1", port), handler) as httpd:
            thread = threading.Thread(target=httpd.serve_forever, daemon=True)
            thread.start()
            try:
                yield httpd.server_address[1]
            finally:
                httpd.shutdown()

    pdf_out = DIST_DIR / PDF_FILENAME
    with serve(DIST_DIR) as port:
        url = f"http://127.0.0.1:{port}/{book_html.name}"
        print(f"    serving {DIST_DIR} on {url}")

        with sync_playwright() as p:
            try:
                browser = p.chromium.launch(channel="chrome", headless=True, timeout=30_000)
            except Exception as e:
                print(f"    system Chrome launch failed ({type(e).__name__}); falling back to bundled chromium")
                print("    (run `uv run playwright install chromium` once if this also fails)")
                browser = p.chromium.launch(headless=True)
            page = browser.new_page(viewport={"width": 1200, "height": 1600})

            # The book must be self-contained: block anything not served from
            # dist/, and fail on any asset that doesn't load. Otherwise a typo'd
            # image path still produces a PDF, with a broken image in it.
            origin = f"http://127.0.0.1:{port}/"
            problems: list[str] = []

            def _route(route):
                if route.request.url.startswith(origin) or route.request.url.startswith("data:"):
                    route.continue_()
                else:
                    problems.append(f"blocked request to another host: {route.request.url}")
                    route.abort()

            page.route("**/*", _route)
            page.on("response", lambda r: r.status >= 400 and problems.append(
                f"HTTP {r.status}: {r.url.removeprefix(origin)}"))
            page.on("pageerror", lambda e: problems.append(f"page error: {e}"))
            page.goto(url, wait_until="load")

            # Paged.js sets this flag when pagination is complete. If it never
            # does, fail: printing anyway risks a silently truncated PDF.
            try:
                page.wait_for_function(
                    "() => window.__pagedjsDone === true",
                    timeout=120_000,
                )
            except Exception:
                fail("paged.js never finished (window.__pagedjsDone not set after 120 s); "
                     "open dist/book.html in a browser and check the console")
            if problems:
                # Paged.js loads images again while laying out pages; report each once
                fail("book.html did not load cleanly:\n    " + "\n    ".join(dict.fromkeys(problems)))

            page_count = page.evaluate(
                "() => document.querySelectorAll('.pagedjs_page').length"
            )
            print(f"    paged.js produced {page_count} sheets")

            # Strip preview-only chrome (shadow, gray bg, page badge) before
            # printing so the PDF is clean. CSS is gated on body.preview-mode.
            page.evaluate("document.body.classList.remove('preview-mode')")

            # tagged=True + outline=True emit a PDF outline (sidebar bookmarks)
            # derived from h1/h2 headings — the navigable TOC panel in PDF viewers.
            page.pdf(
                path=str(pdf_out),
                print_background=True,
                prefer_css_page_size=True,
                margin={"top": "0", "bottom": "0", "left": "0", "right": "0"},
                display_header_footer=False,
                tagged=True,
                outline=True,
            )
            browser.close()
    return pdf_out


def main() -> None:
    import os
    skip_pdf = os.environ.get("SKIP_PDF") == "1"
    skip_mkdocs = os.environ.get("SKIP_MKDOCS") == "1"
    has_mkdocs = (BOOK_DIR / "mkdocs.yml").exists()

    if diagram_scripts():
        render_svg_diagrams()
    if drawio_files():
        render_drawio_diagrams()
    if not diagram_scripts() and not drawio_files():
        print("==> 1/4  No diagram scripts or .drawio files; skipping diagrams")

    if has_mkdocs and not skip_mkdocs:
        build_mkdocs()
    else:
        print(f"==> 2/4  {'SKIP_MKDOCS=1' if skip_mkdocs else 'No mkdocs.yml'}; skipping multi-page site")

    split_readme()
    book_html = build_print_book()

    site = DIST_DIR / "site"
    print()
    print("==> DONE")
    if has_mkdocs and not skip_mkdocs:
        print(f"  Multi-page HTML: {site}/index.html")
    print(f"  Print HTML:      {book_html}  (open this to preview the PDF)")

    if skip_pdf:
        print("  PDF:             SKIPPED (SKIP_PDF=1)")
        return

    try:
        pdf_out = render_pdf(book_html)
        print(f"  PDF:             {pdf_out} ({pdf_out.stat().st_size // 1024} KB)")
    except Exception as e:
        print(f"  PDF:             FAILED ({type(e).__name__}: {e})")
        print(f"                   Re-run with SKIP_PDF=1 to skip; debug headless paged.js separately.")
        sys.exit(1)


if __name__ == "__main__":
    main()
