"""Check local documentation links and German/English public API coverage."""

from collections import Counter
from pathlib import Path
import re
import sys
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
EXCLUDED = {".git", ".style", "stubs"}
PAIRS = [
    "README.md",
    "Bridge/README.md",
    "Configurator/README.md",
    "Device/README.md",
    "Discovery/README.md",
    "Group/README.md",
    "NetworkMap/README.md",
    "actions/README.md",
    "tests/DeviceSimulator/README.md",
    "CHANGELOG.md",
]


def anchors(text):
    result = set(re.findall(r'<a\s+(?:name|id)=["\x27]([^"\x27]+)', text))
    duplicates = Counter()
    for heading in re.findall(r"^#{1,6}\s+(.+)$", text, re.M):
        heading = re.sub(r"<!--.*?-->", "", heading)
        heading = re.sub(r"<[^>]+>", "", heading).strip().lower()
        slug = re.sub(r"[^\w\- ]", "", heading.replace("`", "")).replace(" ", "-")
        index = duplicates[slug]
        duplicates[slug] += 1
        result.add(slug + (f"-{index}" if index else ""))
    return result


def main():
    errors = []
    files = sorted(
        path for path in ROOT.rglob("*.md")
        if not EXCLUDED.intersection(path.relative_to(ROOT).parts)
    )
    link_count = 0
    for path in files:
        text = path.read_text(encoding="utf-8")
        for match in re.finditer(r"!?\[[^\]\n]*\]\(([^\s\)]+)(?:\s+[^\)]*)?\)", text):
            target = match.group(1).strip("<>")
            url = urlsplit(target)
            if url.scheme or target.startswith("//"):
                continue
            link_count += 1
            destination = path.parent / unquote(url.path) if url.path else path
            location = f"{path.relative_to(ROOT)}:{text.count(chr(10), 0, match.start()) + 1}"
            if not destination.exists():
                errors.append(f"{location}: missing target {target}")
            elif url.fragment and destination.suffix.lower() == ".md":
                if unquote(url.fragment) not in anchors(destination.read_text(encoding="utf-8")):
                    errors.append(f"{location}: missing anchor {target}")

    for relative in PAIRS:
        german = ROOT / relative
        english = german.with_name(german.stem + ".en.md")
        if not english.exists():
            errors.append(f"{relative}: English counterpart missing")
            continue
        de = german.read_text(encoding="utf-8")
        en = english.read_text(encoding="utf-8")
        api_pattern = r"\bZ2M_[A-Za-z0-9_]+(?=\s*\()"
        de_apis = set(re.findall(api_pattern, de))
        en_apis = set(re.findall(api_pattern, en))
        if de_apis != en_apis:
            errors.append(
                f"{relative}: API coverage differs; DE only={sorted(de_apis-en_apis)}, "
                f"EN only={sorted(en_apis-de_apis)}"
            )

    print(f"Checked {len(files)} Markdown files, {link_count} local links and {len(PAIRS)} language pairs.")
    for error in errors:
        print(error)
    if not errors:
        print("Documentation checks passed. External URLs and rendering are not covered.")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
