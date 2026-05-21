#!/usr/bin/env python3
"""
WordPress to Statamic content migration script.
Reads the SQL dump and creates Statamic content entries from WordPress data.
"""

import os
import re
import uuid
import json
import html
from datetime import datetime
from html.parser import HTMLParser
from pathlib import Path

# ─── Configuration ────────────────────────────────────────────────────────────

SQL_FILE = Path(__file__).parent / "database.sql"
REPO_ROOT = Path(__file__).parent.parent

PAGES_DIR = REPO_ROOT / "content/collections/pages"
BLOG_DIR = REPO_ROOT / "content/collections/blog"
PAGES_TREE = REPO_ROOT / "content/trees/collections/pages.yaml"

TABLE_PREFIX = "SERVMASK_PREFIX_"

# Template mapping by slug (prefix matches with *)
TEMPLATE_MAP = {
    "home": "home",
    "about-us": "default",
    "community-ambulance-companys-history": "history-timeline",
    "prevention-programs": "programs-index",
    "car-seat-inspection": "default",
    "community-education": "default",
    "community-safety": "default",
    "free-turkeys": "default",
    "life-jacket": "default",
    "public-aeds": "default",
    "public-cpr": "default",
    "public-narcan": "default",
    "shed-the-meds": "default",
    "shred-day": "default",
    "our-team": "default",
    "chiefs-corner": "chiefs-corner",
    "our-officers-and-board-members": "team",
    "in-memoriam": "team",
    "charter-members": "team",
    "youth-squad": "default",
    "cac-members-only": "default",
    "events": "events-index",
    "annual-5k": "5k",
    "join-community": "form-join",
    "join-youth-squad": "form-youth",
    "contact-us": "form-contact",
    "community-gallery": "gallery",
    "installation-dinner-videos": "video-gallery",
}

# Prefix-match slugs (slugs that start with key get the mapped template)
TEMPLATE_PREFIX_MAP = {
    "car-seat-inspection": "default",
    "community-safety": "default",
    "free-turkeys": "default",
    "life-jacket": "default",
    "public-cpr": "default",
    "public-narcan": "default",
    "shred-day": "default",
    "charter-members": "team",
    "annual-5k": "5k",
}


def get_template(slug):
    """Return the Statamic template for a given WP slug."""
    if slug in TEMPLATE_MAP:
        return TEMPLATE_MAP[slug]
    for prefix, template in TEMPLATE_PREFIX_MAP.items():
        if slug.startswith(prefix):
            return template
    return "default"


# ─── HTML → plain text extractor ──────────────────────────────────────────────

class HTMLToTextParser(HTMLParser):
    """Converts HTML to rough markdown-style plain text."""

    BLOCK_TAGS = {"p", "div", "section", "article", "header", "footer",
                  "h1", "h2", "h3", "h4", "h5", "h6",
                  "ul", "ol", "li", "blockquote", "pre", "br",
                  "table", "tr", "td", "th", "thead", "tbody"}

    HEADING_MAP = {"h1": "#", "h2": "##", "h3": "###",
                   "h4": "####", "h5": "#####", "h6": "######"}

    def __init__(self):
        super().__init__()
        self.result = []
        self.current_tag = None
        self.tag_stack = []
        self.in_list = False
        self.list_item = False
        self._skip_tags = {"script", "style", "noscript"}
        self._skip_depth = 0

    def handle_starttag(self, tag, attrs):
        tag = tag.lower()
        if tag in self._skip_tags:
            self._skip_depth += 1
            return
        self.tag_stack.append(tag)
        if tag in self.HEADING_MAP:
            self.result.append(f"\n{self.HEADING_MAP[tag]} ")
        elif tag == "p":
            self.result.append("\n")
        elif tag == "br":
            self.result.append("\n")
        elif tag == "li":
            self.result.append("\n- ")
        elif tag == "ul" or tag == "ol":
            self.result.append("\n")
        elif tag == "strong" or tag == "b":
            self.result.append("**")
        elif tag == "em" or tag == "i":
            self.result.append("*")
        elif tag == "a":
            attrs_dict = dict(attrs)
            href = attrs_dict.get("href", "")
            self.result.append("[")
            self._pending_href = href
        elif tag == "img":
            attrs_dict = dict(attrs)
            alt = attrs_dict.get("alt", "")
            src = attrs_dict.get("src", "")
            # Convert wp-content uploads path to local assets reference
            src = re.sub(r'https?://[^/]+/wp-content/uploads/', '/assets/', src)
            self.result.append(f"\n![{alt}]({src})\n")

    def handle_endtag(self, tag):
        tag = tag.lower()
        if self._skip_depth > 0:
            if tag in self._skip_tags:
                self._skip_depth -= 1
            return
        if self.tag_stack and self.tag_stack[-1] == tag:
            self.tag_stack.pop()
        if tag in self.HEADING_MAP:
            self.result.append("\n")
        elif tag == "p":
            self.result.append("\n")
        elif tag == "strong" or tag == "b":
            self.result.append("**")
        elif tag == "em" or tag == "i":
            self.result.append("*")
        elif tag == "a":
            href = getattr(self, "_pending_href", "")
            # Convert internal WP links to relative
            href = re.sub(r'https?://communityamb\.org/', '/', href)
            self.result.append(f"]({href})")
            self._pending_href = ""

    def handle_data(self, data):
        if self._skip_depth > 0:
            return
        self.result.append(data)

    def get_text(self):
        text = "".join(self.result)
        # Collapse lines that are only whitespace/tabs (from Elementor indented HTML)
        lines = text.split("\n")
        cleaned = []
        for line in lines:
            stripped = line.strip()
            cleaned.append(stripped if stripped else "")
        text = "\n".join(cleaned)
        # Clean up excessive blank lines
        text = re.sub(r'\n{3,}', '\n\n', text)
        return text.strip()


def html_to_markdown(html_content):
    """Convert HTML string to rough markdown."""
    if not html_content or not html_content.strip():
        return ""
    # Decode HTML entities first
    content = html.unescape(html_content)
    parser = HTMLToTextParser()
    try:
        parser.feed(content)
    except Exception:
        pass
    return parser.get_text()


def is_elementor_content(post_content):
    """Detect if the page uses Elementor."""
    if not post_content:
        return False
    return (
        '[elementor' in post_content
        or 'elementor-section' in post_content
        or 'elementor-widget' in post_content
    )


def extract_text_from_elementor(elementor_data):
    """
    Extract readable text from Elementor JSON data.
    Returns plain text content with TODO comment.
    """
    if not elementor_data:
        return None

    texts = []
    try:
        data = json.loads(elementor_data)

        def walk(node):
            if isinstance(node, dict):
                # Look for text-type widgets
                widget_type = node.get("widgetType", "")
                settings = node.get("settings", {})

                if widget_type in ("heading", "text-editor", "text", "icon-box",
                                   "accordion", "toggle", "tab", "icon-list"):
                    for key in ("title", "editor", "text", "description",
                                "tab_content", "accordion_items"):
                        val = settings.get(key)
                        if isinstance(val, str) and val.strip():
                            cleaned = html_to_markdown(val)
                            if cleaned:
                                texts.append(cleaned)
                        elif isinstance(val, list):
                            for item in val:
                                if isinstance(item, dict):
                                    for k2 in ("tab_title", "tab_content",
                                               "accordion_tab_title", "accordion_content",
                                               "text", "link_text"):
                                        v2 = item.get(k2, "")
                                        if isinstance(v2, str) and v2.strip():
                                            cleaned = html_to_markdown(v2)
                                            if cleaned:
                                                texts.append(cleaned)

                # Recurse into children / elements
                for key in ("elements", "tabs", "accordion"):
                    children = node.get(key, [])
                    if isinstance(children, list):
                        for child in children:
                            walk(child)

            elif isinstance(node, list):
                for item in node:
                    walk(item)

        walk(data)
    except (json.JSONDecodeError, TypeError):
        pass

    return "\n\n".join(texts) if texts else None


# ─── SQL Parser ───────────────────────────────────────────────────────────────

def unquote_sql_string(s):
    """Remove surrounding SQL quotes and unescape MySQL escape sequences."""
    if s == "NULL":
        return None
    if s.startswith("'") and s.endswith("'"):
        s = s[1:-1]
    # Unescape MySQL escape sequences
    s = s.replace("\\'", "'")
    s = s.replace('\\"', '"')
    s = s.replace("\\\\", "\\")
    s = s.replace("\\n", "\n")
    s = s.replace("\\r", "\r")
    s = s.replace("\\t", "\t")
    s = s.replace("\\0", "\0")
    s = s.replace("\\Z", "\x1a")
    return s


def parse_values_line(line):
    """
    Parse a MySQL INSERT INTO ... VALUES (...) line.
    Returns a list of string values. Handles embedded escaped quotes and newlines.
    """
    # Strip the INSERT INTO `table` VALUES ( ... ); wrapper
    m = re.match(r"INSERT INTO `[^`]+` VALUES \((.*)\);?\s*$", line, re.DOTALL)
    if not m:
        return None
    raw = m.group(1)

    values = []
    i = 0
    length = len(raw)

    while i < length:
        # Skip whitespace between fields
        while i < length and raw[i] in (' ', '\t'):
            i += 1
        if i >= length:
            break

        if raw[i] == "'":
            # Quoted string — scan for the closing unescaped quote
            i += 1
            buf = []
            while i < length:
                c = raw[i]
                if c == '\\' and i + 1 < length:
                    nc = raw[i + 1]
                    if nc == "'":
                        buf.append("'")
                    elif nc == '"':
                        buf.append('"')
                    elif nc == '\\':
                        buf.append('\\')
                    elif nc == 'n':
                        buf.append('\n')
                    elif nc == 'r':
                        buf.append('\r')
                    elif nc == 't':
                        buf.append('\t')
                    elif nc == '0':
                        buf.append('\0')
                    elif nc == 'Z':
                        buf.append('\x1a')
                    else:
                        buf.append(nc)
                    i += 2
                elif c == "'":
                    # Check for doubled quote ''
                    if i + 1 < length and raw[i + 1] == "'":
                        buf.append("'")
                        i += 2
                    else:
                        i += 1
                        break
                else:
                    buf.append(c)
                    i += 1
            values.append("".join(buf))
        else:
            # Unquoted value (number, NULL)
            end = i
            while end < length and raw[end] not in (',', ')'):
                end += 1
            token = raw[i:end].strip()
            values.append(None if token == "NULL" else token)
            i = end

        # Skip comma separator
        while i < length and raw[i] in (' ', '\t'):
            i += 1
        if i < length and raw[i] == ',':
            i += 1

    return values


# ─── Main extraction logic ─────────────────────────────────────────────────────

def read_posts_and_meta(sql_file):
    """
    Stream through the SQL file and extract:
    - posts: dict of post_id -> post dict (page + blog post type, published only)
    - postmeta: dict of post_id -> {meta_key: meta_value}
    """
    posts = {}
    all_postmeta = {}

    # Indices for SERVMASK_PREFIX_posts columns:
    # 0:ID, 1:post_author, 2:post_date, 3:post_date_gmt, 4:post_content,
    # 5:post_title, 6:post_excerpt, 7:post_status, 8:comment_status,
    # 9:ping_status, 10:post_password, 11:post_name, 12:to_ping,
    # 13:pinged, 14:post_modified, 15:post_modified_gmt,
    # 16:post_content_filtered, 17:post_parent, 18:guid, 19:menu_order,
    # 20:post_type, 21:post_mime_type, 22:comment_count

    posts_table = f"`{TABLE_PREFIX}posts`"
    postmeta_table = f"`{TABLE_PREFIX}postmeta`"

    current_section = None

    print(f"Scanning {sql_file} ...")
    with open(sql_file, "r", encoding="utf-8", errors="replace") as f:
        for lineno, line in enumerate(f, 1):
            line = line.rstrip("\n")

            if not line.startswith("INSERT INTO"):
                continue

            if posts_table in line:
                current_section = "posts"
            elif postmeta_table in line:
                current_section = "postmeta"
            else:
                continue

            if current_section == "posts":
                vals = parse_values_line(line)
                if not vals or len(vals) < 21:
                    continue
                post_id = vals[0]
                post_status = vals[7]
                post_type = vals[20]

                if post_status != "publish":
                    continue
                if post_type not in ("page", "post"):
                    continue

                posts[post_id] = {
                    "ID": post_id,
                    "post_date": vals[2],
                    "post_content": vals[4],
                    "post_title": vals[5],
                    "post_excerpt": vals[6],
                    "post_status": post_status,
                    "post_name": vals[11],
                    "post_parent": vals[17],
                    "post_type": post_type,
                }

            elif current_section == "postmeta":
                vals = parse_values_line(line)
                if not vals or len(vals) < 4:
                    continue
                # 0:meta_id, 1:post_id, 2:meta_key, 3:meta_value
                pid = vals[1]
                meta_key = vals[2]
                meta_value = vals[3]

                if meta_key in ("_elementor_data",):
                    if pid not in all_postmeta:
                        all_postmeta[pid] = {}
                    all_postmeta[pid][meta_key] = meta_value

    print(f"  Found {len(posts)} published pages/posts")
    print(f"  Found elementor meta for {len(all_postmeta)} posts")
    return posts, all_postmeta


# ─── File generation ──────────────────────────────────────────────────────────

def slugify(s):
    """Basic slugify."""
    s = s.lower()
    s = re.sub(r'[^a-z0-9-]', '-', s)
    s = re.sub(r'-+', '-', s)
    return s.strip('-')


def yaml_escape(s):
    """Escape a string for YAML double-quoted scalar."""
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def make_frontmatter(fields):
    """Generate YAML frontmatter string from a dict."""
    lines = ["---"]
    for key, value in fields.items():
        if value is None:
            lines.append(f"{key}:")
        elif isinstance(value, bool):
            lines.append(f"{key}: {'true' if value else 'false'}")
        elif isinstance(value, str):
            # Use double-quoted strings if needed
            if any(c in value for c in (':', '#', '[', ']', '{', '}', ',', '&', '*',
                                         '?', '|', '-', '<', '>', '=', '!', '%',
                                         '@', '`', '"', "'")):
                lines.append(f'{key}: "{yaml_escape(value)}"')
            elif '\n' in value:
                lines.append(f'{key}: "{yaml_escape(value)}"')
            else:
                lines.append(f"{key}: {value}")
        else:
            lines.append(f"{key}: {value}")
    lines.append("---")
    return "\n".join(lines)


def process_content(post, postmeta):
    """
    Return (body_markdown, used_elementor: bool)
    """
    raw_content = post.get("post_content", "") or ""
    post_id = post["ID"]
    slug = post["post_name"]

    # Check if Elementor
    if is_elementor_content(raw_content):
        elementor_data = (postmeta.get(post_id) or {}).get("_elementor_data")
        extracted = extract_text_from_elementor(elementor_data)
        if extracted:
            body = (
                f"<!-- TODO: This page was built with Elementor. "
                f"Content extracted from widget data — review and reformat for Statamic Bard. -->\n\n"
                + extracted
            )
        else:
            # Fall back to raw HTML → markdown
            body = (
                f"<!-- TODO: This page was built with Elementor and automatic text extraction failed. "
                f"Please manually rebuild this page in Statamic. -->\n\n"
                + html_to_markdown(raw_content)
            )
        return body, True

    # Normal HTML content
    if raw_content and raw_content.strip():
        return html_to_markdown(raw_content), False

    return "", False


def write_page(post, postmeta, existing_home_id=None):
    """Write a Statamic page markdown file. Returns (slug, post_id, file_path)."""
    slug = post["post_name"]
    title = post["post_title"]
    post_id = post["ID"]

    # Special case: home page keeps the existing `home` id so the tree works
    if slug == "home" and existing_home_id:
        entry_id = existing_home_id
    else:
        entry_id = str(uuid.uuid4())

    template = get_template(slug)
    body, used_elementor = process_content(post, postmeta)

    frontmatter = make_frontmatter({
        "title": title,
        "id": entry_id,
        "template": template,
        "blueprint": "page",
    })

    file_content = frontmatter + "\n" + body + "\n" if body else frontmatter + "\n"
    file_path = PAGES_DIR / f"{slug}.md"

    with open(file_path, "w", encoding="utf-8") as f:
        f.write(file_content)

    marker = " [elementor]" if used_elementor else ""
    print(f"  [page] {slug}.md  →  template:{template}{marker}")
    return entry_id, post_id, file_path


def write_blog_post(post, postmeta):
    """Write a Statamic blog post markdown file. Returns (slug, entry_id)."""
    slug = post["post_name"]
    title = post["post_title"]
    post_id = post["ID"]
    entry_id = str(uuid.uuid4())
    template = "blog-single"

    # Parse date
    try:
        dt = datetime.strptime(post["post_date"], "%Y-%m-%d %H:%M:%S")
        date_prefix = dt.strftime("%Y-%m-%d")
    except (ValueError, TypeError):
        date_prefix = "2023-01-01"

    body, used_elementor = process_content(post, postmeta)

    frontmatter = make_frontmatter({
        "title": title,
        "id": entry_id,
        "template": template,
        "blueprint": "blog",
        "date": date_prefix,
    })

    file_content = frontmatter + "\n" + body + "\n" if body else frontmatter + "\n"
    filename = f"{date_prefix}.{slug}.md"
    file_path = BLOG_DIR / filename

    with open(file_path, "w", encoding="utf-8") as f:
        f.write(file_content)

    marker = " [elementor]" if used_elementor else ""
    print(f"  [blog] {filename}{marker}")
    return slug, entry_id


# ─── Page tree builder ────────────────────────────────────────────────────────

def build_page_tree(posts, page_id_to_entry_id, page_id_to_slug):
    """
    Build a Statamic page tree YAML from WP post_parent relationships.
    Returns a YAML string.
    """
    # Map post_id -> list of child post_ids
    children_map = {}
    root_ids = []

    for post_id, post in posts.items():
        if post["post_type"] != "page":
            continue
        parent_id = post["post_parent"]
        if parent_id == "0" or parent_id is None:
            root_ids.append(post_id)
        else:
            if parent_id not in children_map:
                children_map[parent_id] = []
            children_map[parent_id].append(post_id)

    def render_node(post_id, depth=0):
        entry_id = page_id_to_entry_id.get(post_id)
        if not entry_id:
            return ""
        pad = "  " * depth
        lines = [f"{pad}-"]
        lines.append(f"{pad}  entry: {entry_id}")
        kids = children_map.get(post_id, [])
        if kids:
            lines.append(f"{pad}  children:")
            for kid in sorted(kids, key=lambda k: int(posts[k].get("menu_order", 0))
                              if k in posts and posts[k].get("menu_order", "0").isdigit() else 0):
                child_str = render_node(kid, depth + 1)
                if child_str:
                    lines.append(child_str)
        return "\n".join(lines)

    # Sort root pages: home first, then by post_id
    def root_sort_key(pid):
        slug = page_id_to_slug.get(pid, "")
        if slug == "home":
            return (0, 0)
        return (1, int(pid) if pid and pid.isdigit() else 0)

    root_ids_sorted = sorted(root_ids, key=root_sort_key)

    yaml_lines = ["tree:"]
    for pid in root_ids_sorted:
        node_str = render_node(pid)
        if node_str:
            yaml_lines.append(node_str)

    return "\n".join(yaml_lines) + "\n"


# ─── Entry point ──────────────────────────────────────────────────────────────

def main():
    print("=" * 60)
    print("WordPress → Statamic Content Migration")
    print("=" * 60)

    # Ensure output dirs exist
    PAGES_DIR.mkdir(parents=True, exist_ok=True)
    BLOG_DIR.mkdir(parents=True, exist_ok=True)

    # Step 1: Parse SQL
    posts, postmeta = read_posts_and_meta(SQL_FILE)

    pages = {pid: p for pid, p in posts.items() if p["post_type"] == "page"}
    blog_posts = {pid: p for pid, p in posts.items() if p["post_type"] == "post"}

    print(f"\nPages: {len(pages)}")
    print(f"Blog posts: {len(blog_posts)}")

    # Step 2: Write pages
    print("\n--- Writing Pages ---")
    page_id_to_entry_id = {}  # post_id -> statamic entry id
    page_id_to_slug = {}

    for post_id, post in sorted(pages.items(), key=lambda x: int(x[0]) if x[0].isdigit() else 0):
        slug = post["post_name"]
        page_id_to_slug[post_id] = slug

        # For home page, use the existing "home" id to keep tree working
        if slug == "home":
            entry_id, _, _ = write_page(post, postmeta, existing_home_id="home")
        else:
            entry_id, _, _ = write_page(post, postmeta)

        page_id_to_entry_id[post_id] = entry_id

    # Step 3: Write blog posts
    print("\n--- Writing Blog Posts ---")
    for post_id, post in sorted(blog_posts.items(), key=lambda x: x[1]["post_date"]):
        write_blog_post(post, postmeta)

    # Step 4: Update page tree
    print("\n--- Building Page Tree ---")
    tree_yaml = build_page_tree(posts, page_id_to_entry_id, page_id_to_slug)
    with open(PAGES_TREE, "w", encoding="utf-8") as f:
        f.write(tree_yaml)
    print(f"  Written: {PAGES_TREE}")

    print("\n✓ Migration complete.")
    print(f"  Pages written: {len(pages)}")
    print(f"  Blog posts written: {len(blog_posts)}")


if __name__ == "__main__":
    main()
