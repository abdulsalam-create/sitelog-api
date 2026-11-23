#!/usr/bin/env python3
"""Build docs/api.docx from docs/api.md. Pure stdlib, no dependencies."""

import html
import re
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
SRC = HERE / "api.md"
OUT = HERE / "api.docx"

META = {
    "project": "SiteLog API",
    "subtitle": "Network Site Maintenance Reporting and Fault Tracking",
    "kind": "API Reference and Developer Handbook",
    "version": "1.0",
    "date": "23 November 2026",
    "author": "Abdulsalam Abdulsalam Ayobami",
    "stack": "Laravel 12, PHP 8.3, MySQL 8, Sanctum, Pest",
    "repo": "https://github.com/abdulsalam-create/sitelog-api",
}

NAVY = "1F3864"
BLUE = "2E74B5"
BORDER = "BFBFBF"
CODE_BG = "F2F2F2"

NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'


def esc(text):
    return html.escape(text, quote=False)


def inline_runs(text):
    parts = re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", text)
    runs = []
    for part in parts:
        if not part:
            continue
        if part.startswith("**") and part.endswith("**"):
            runs.append(f"<w:r><w:rPr><w:b/></w:rPr><w:t xml:space=\"preserve\">{esc(part[2:-2])}</w:t></w:r>")
        elif part.startswith("`") and part.endswith("`"):
            runs.append(
                f"<w:r><w:rPr><w:rFonts w:ascii=\"Consolas\" w:hAnsi=\"Consolas\"/></w:rPr>"
                f"<w:t xml:space=\"preserve\">{esc(part[1:-1])}</w:t></w:r>"
            )
        else:
            runs.append(f"<w:r><w:t xml:space=\"preserve\">{esc(part)}</w:t></w:r>")
    return "".join(runs)


class Builder:
    def __init__(self):
        self.body = []
        self.list_num_id = 2  # 1 is the shared bullet list

    def para(self, text="", style=None, bullet=False, ordered=None, mono=False, shade=False, page_break=False, keep_next=False):
        ppr = ["<w:pPr>"]
        if style:
            ppr.append(f'<w:pStyle w:val="{style}"/>')
        if bullet:
            ppr.append('<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>')
            ppr.append('<w:ind w:left="720" w:hanging="360"/>')
        if ordered is not None:
            ppr.append(f'<w:numPr><w:ilvl w:val="0"/><w:numId w:val="{ordered}"/></w:numPr>')
            ppr.append('<w:ind w:left="720" w:hanging="360"/>')
        if keep_next:
            ppr.append("<w:keepNext/>")
        ppr.append("</w:pPr>")
        runs = [f"<w:r><w:t xml:space=\"preserve\">{esc(text)}</w:t></w:r>"]
        if page_break:
            runs.append('<w:r><w:br w:type="page"/></w:r>')
        self.body.append(f"<w:p>{''.join(ppr)}{''.join(runs)}</w:p>")

    def code_block(self, block):
        for line in block:
            ppr = '<w:pPr><w:pStyle w:val="CodeBlock"/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>'
            content = esc(line) if line else "&#160;"
            self.body.append(
                f"<w:p>{ppr}<w:r><w:rPr><w:rFonts w:ascii=\"Consolas\" w:hAnsi=\"Consolas\"/></w:rPr>"
                f"<w:t xml:space=\"preserve\">{content}</w:t></w:r></w:p>"
            )

    def table(self, rows):
        rows = [r for r in rows if not all(re.fullmatch(r":?-{2,}:?", c) for c in r)]
        if not rows:
            return
        cols = max(len(r) for r in rows)
        widths = {2: [3200, 5800], 3: [2400, 3200, 3400], 4: [1600, 1600, 3100, 2700]}.get(cols, [int(9000 / cols)] * cols)
        grid = "".join(f'<w:gridCol w:w="{w}"/>' for w in widths)
        borders = "".join(
            f'<w:{side} w:val="single" w:sz="4" w:space="0" w:color="{BORDER}"/>'
            for side in ("top", "left", "bottom", "right", "insideH", "insideV")
        )
        tbl = [
            "<w:tbl>",
            "<w:tblPr>"
            '<w:tblW w:w="0" w:type="auto"/>'
            f"<w:tblBorders>{borders}</w:tblBorders>"
            '<w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="108" w:type="dxa"/>'
            '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar>'
            "</w:tblPr>",
            f"<w:tblGrid>{grid}</w:tblGrid>",
        ]
        for ridx, row in enumerate(rows):
            trpr = "<w:trPr><w:tblHeader/></w:trPr>" if ridx == 0 else ""
            cells = []
            for cidx, cell in enumerate(row + [""] * (cols - len(row))):
                tcpr = f'<w:tcPr><w:tcW w:w="{widths[cidx]}" w:type="dxa"/>'
                if ridx == 0:
                    tcpr += f'<w:shd w:val="clear" w:color="auto" w:fill="{NAVY}"/>'
                tcpr += "</w:tcPr>"
                if ridx == 0:
                    content = inline_runs(cell).replace("<w:r>", '<w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/></w:rPr>', 1).replace('<w:r><w:rPr><w:rFonts', '<w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:rFonts')
                else:
                    content = inline_runs(cell)
                cells.append(
                    f"<w:tc>{tcpr}<w:p><w:pPr><w:spacing w:after=\"40\" w:line=\"240\" w:lineRule=\"auto\"/></w:pPr>"
                    f"{content}</w:p></w:tc>"
                )
            tbl.append(f"<w:tr>{trpr}{''.join(cells)}</w:tr>")
        tbl.append("</w:tbl>")
        self.body.append("".join(tbl))
        self.para(style="Spacer")

    def toc_field(self):
        runs = (
            '<w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>'
            '<w:r><w:instrText xml:space="preserve"> TOC \\o "1-2" \\h \\z \\u </w:instrText></w:r>'
            '<w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            '<w:r><w:t>Table of contents will appear here when the document is opened.</w:t></w:r>'
            '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
        )
        self.body.append(f"<w:p><w:pPr><w:spacing w:after=\"120\"/></w:pPr>{runs}</w:p>")


def build():
    src = SRC.read_text(encoding="utf-8").split("\n")
    b = Builder()

    # Title page
    b.para("SiteLog API", style="Title")
    b.para("Network Site Maintenance Reporting and Fault Tracking", style="Subtitle")
    b.para("API Reference and Developer Handbook", style="Subtitle")
    b.para(style="Spacer")
    b.para(style="Spacer")
    b.table([[k, v] for k, v in [
        ("Project", META["project"]),
        ("Version", META["version"]),
        ("Date", META["date"]),
        ("Author", META["author"]),
        ("Stack", META["stack"]),
        ("Repository", META["repo"]),
    ]])
    b.para(page_break=True)

    # Table of contents
    b.para("Contents", style="TocHeading")
    b.toc_field()
    b.para(page_break=True)

    # Body
    i = 0
    first_h1 = True
    lines = src
    while i < len(lines):
        line = lines[i]
        if line.startswith("```"):
            block = []
            i += 1
            while i < len(lines) and not lines[i].startswith("```"):
                block.append(lines[i])
                i += 1
            i += 1
            b.code_block(block)
        elif line.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                rows.append([c.strip() for c in lines[i].strip().strip("|").split("|")])
                i += 1
            b.table(rows)
        elif line.startswith("### "):
            b.para(line[4:], style="Heading3", keep_next=True)
            i += 1
        elif line.startswith("## "):
            b.para(line[3:], style="Heading2", keep_next=True)
            i += 1
        elif line.startswith("# "):
            if not first_h1:
                b.para(page_break=True)
            first_h1 = False
            b.para(line[2:], style="Heading1", keep_next=True)
            i += 1
        elif re.match(r"^\d+\.\s", line):
            num_id = b.list_num_id
            b.list_num_id += 1
            while i < len(lines) and re.match(r"^\d+\.\s", lines[i]):
                b.para(re.sub(r"^\d+\.\s", "", lines[i]), ordered=num_id)
                i += 1
        elif line.startswith("- "):
            b.para(line[2:], bullet=True)
            i += 1
        elif line.startswith("---"):
            i += 1
        elif line.strip() == "":
            i += 1
        else:
            text = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", line)
            b.para(text)
            i += 1

    document = (
        f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        f'<w:document {NS}>'
        f'<w:body>{"".join(b.body)}'
        '<w:sectPr>'
        '<w:footerReference w:type="default" r:id="rIdFooter"/>'
        '<w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="720" w:footer="720" w:gutter="0"/>'
        "</w:sectPr>"
        "</w:body></w:document>"
    )

    styles = (
        f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        f'<w:styles {NS}>'
        '<w:docDefaults>'
        '<w:rPrDefault><w:rPr>'
        '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/>'
        '<w:sz w:val="22"/><w:szCs w:val="22"/>'
        "</w:rPr></w:rPrDefault>"
        '<w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
        "</w:docDefaults>"
        '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
        '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:qFormat/>'
        '<w:pPr><w:spacing w:before="240" w:after="240"/><w:jc w:val="center"/></w:pPr>'
        '<w:rPr><w:rFonts w:ascii="Calibri Light" w:hAnsi="Calibri Light"/>'
        f'<w:b/><w:color w:val="{NAVY}"/><w:sz w:val="56"/><w:szCs w:val="56"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:qFormat/>'
        '<w:pPr><w:spacing w:after="120"/><w:jc w:val="center"/></w:pPr>'
        f'<w:rPr><w:color w:val="{BLUE}"/><w:sz w:val="28"/><w:szCs w:val="28"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:qFormat/>'
        '<w:pPr><w:keepNext/><w:spacing w:before="360" w:after="200"/><w:outlineLvl w:val="0"/></w:pPr>'
        f'<w:rPr><w:b/><w:color w:val="{NAVY}"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:qFormat/>'
        '<w:pPr><w:keepNext/><w:spacing w:before="280" w:after="140"/><w:outlineLvl w:val="1"/></w:pPr>'
        f'<w:rPr><w:b/><w:color w:val="{BLUE}"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:qFormat/>'
        '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="2"/></w:pPr>'
        f'<w:rPr><w:b/><w:color w:val="{BLUE}"/><w:sz w:val="23"/><w:szCs w:val="23"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="TocHeading"><w:name w:val="TocHeading"/><w:basedOn w:val="Heading1"/>'
        '<w:next w:val="Normal"/><w:qFormat/></w:style>'
        '<w:style w:type="paragraph" w:styleId="CodeBlock"><w:name w:val="CodeBlock"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/>'
        f'<w:pPr><w:shd w:val="clear" w:color="auto" w:fill="{CODE_BG}"/>'
        '<w:ind w:left="120" w:right="120"/><w:spacing w:after="0"/></w:pPr>'
        '<w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>'
        '<w:style w:type="paragraph" w:styleId="Spacer"><w:name w:val="Spacer"/><w:basedOn w:val="Normal"/>'
        '<w:next w:val="Normal"/><w:pPr><w:spacing w:after="80"/></w:pPr>'
        '<w:rPr><w:sz w:val="8"/></w:rPr></w:style>'
        "</w:styles>"
    )

    numbering = (
        f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        f'<w:numbering {NS}>'
        '<w:abstractNum w:abstractNumId="0">'
        '<w:multiLevelType w:val="hybridMultilevel"/>'
        '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/>'
        '<w:lvlJc w:val="left"/>'
        '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>'
        '<w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr></w:lvl>'
        "</w:abstractNum>"
        '<w:abstractNum w:abstractNumId="1">'
        '<w:multiLevelType w:val="hybridMultilevel"/>'
        '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/>'
        '<w:lvlJc w:val="left"/>'
        '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:lvl>'
        "</w:abstractNum>"
        '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
        + "".join(
            f'<w:num w:numId="{n}"><w:abstractNumId w:val="1"/></w:num>'
            for n in range(2, b.list_num_id)
        )
        + "</w:numbering>"
    )

    footer = (
        f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        f'<w:ftr {NS}>'
        '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="0"/></w:pPr>'
        '<w:r><w:t xml:space="preserve">Page </w:t></w:r>'
        '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
        '<w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
        '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
        '<w:r><w:t xml:space="preserve"> of </w:t></w:r>'
        '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
        '<w:r><w:instrText xml:space="preserve"> NUMPAGES </w:instrText></w:r>'
        '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
        "</w:p></w:ftr>"
    )

    settings = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
        f'<w:settings {NS}>'
        '<w:updateFields w:val="true"/>'
        '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>'
        "</w:settings>"
    )

    content_types = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        '<Default Extension="xml" ContentType="application/xml"/>'
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
        '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
        '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        "</Types>"
    )

    rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        "</Relationships>"
    )

    doc_rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        "</Relationships>"
    )

    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", content_types)
        z.writestr("_rels/.rels", rels)
        z.writestr("word/document.xml", document)
        z.writestr("word/styles.xml", styles)
        z.writestr("word/numbering.xml", numbering)
        z.writestr("word/settings.xml", settings)
        z.writestr("word/footer1.xml", footer)
        z.writestr("word/_rels/document.xml.rels", doc_rels)

    print(f"wrote {OUT.name} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    build()
