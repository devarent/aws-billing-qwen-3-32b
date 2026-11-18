# -*- coding: utf-8 -*-
"""Render laporan Praktikum 3 ke .docx dari konten.py."""

from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import os
import konten

OUT_DIR = os.path.dirname(os.path.abspath(__file__))
DOCX = os.path.join(OUT_DIR, "Praktik3_NAMA_NPM_Ganjil.docx")


def shade(paragraph, fill="F2F2F2"):
    pPr = paragraph._p.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), fill)
    pPr.append(shd)


def borders(paragraph, color="A6A6A6", sz="4", style="single"):
    pPr = paragraph._p.get_or_add_pPr()
    pBdr = OxmlElement("w:pBdr")
    for edge in ("top", "left", "bottom", "right"):
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), style)
        el.set(qn("w:sz"), sz)
        el.set(qn("w:space"), "4")
        el.set(qn("w:color"), color)
        pBdr.append(el)
    pPr.append(pBdr)


def mono_font(run, size=9):
    run.font.name = "Consolas"
    run.font.size = Pt(size)
    run.font.color.rgb = RGBColor(0x1A, 0x1A, 0x1A)
    rPr = run._element.get_or_add_rPr()
    rFonts = rPr.find(qn("w:rFonts"))
    if rFonts is None:
        rFonts = OxmlElement("w:rFonts")
        rPr.append(rFonts)
    for attr in ("w:ascii", "w:hAnsi", "w:eastAsia"):
        rFonts.set(qn(attr), "Consolas")


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run()
    b = OxmlElement("w:fldChar"); b.set(qn("w:fldCharType"), "begin")
    i = OxmlElement("w:instrText"); i.set(qn("xml:space"), "preserve"); i.text = " PAGE "
    s = OxmlElement("w:fldChar"); s.set(qn("w:fldCharType"), "separate")
    e = OxmlElement("w:fldChar"); e.set(qn("w:fldCharType"), "end")
    for el in (b, i, s, e):
        run._r.append(el)
    run.font.size = Pt(9)


doc = Document()
sec = doc.sections[0]
sec.page_width, sec.page_height = Cm(21.0), Cm(29.7)
sec.top_margin = Cm(2.5)
sec.bottom_margin = Cm(2.5)
sec.left_margin = Cm(3.0)
sec.right_margin = Cm(2.5)
sec.different_first_page_header_footer = True

style = doc.styles["Normal"]
style.font.name = "Times New Roman"
style.font.size = Pt(11.5)
style.element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")

hp = sec.header.paragraphs[0]
hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
hr = hp.add_run("Praktikum 3 - Instruksi Aritmatika dan Logika (lanjutan)")
hr.font.size = Pt(8.5)
hr.font.color.rgb = RGBColor(0x7F, 0x7F, 0x7F)
add_page_number(sec.footer.paragraphs[0])


def add_para(text, opt=None):
    opt = opt or {}
    p = doc.add_paragraph()
    pf = p.paragraph_format
    pf.space_after = Pt(opt.get("space", 6))
    pf.space_before = Pt(0)
    pf.line_spacing = 1.25
    if opt.get("indent"):
        pf.first_line_indent = Cm(opt["indent"])
    if opt.get("just"):
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    r = p.add_run(text)
    r.font.size = Pt(opt.get("size", 11.5))
    r.bold = opt.get("bold", False)
    r.italic = opt.get("italic", False)


def add_list(text, opt=None, numbered=False, n=None):
    opt = opt or {}
    if numbered:
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(0.85)
        p.paragraph_format.first_line_indent = Cm(-0.6)
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.line_spacing = 1.2
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        r = p.add_run("%d.  " % n + text)
        r.font.size = Pt(11.5)
        return
    p = doc.add_paragraph(style="List Bullet")
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.line_spacing = 1.2
    if opt.get("prefix"):
        r0 = p.add_run(opt["prefix"])
        r0.bold = True
        r0.font.size = Pt(11.5)
    r = p.add_run(text)
    r.font.size = Pt(11.5)


def add_code(text):
    p = doc.add_paragraph()
    pf = p.paragraph_format
    pf.space_before = Pt(4)
    pf.space_after = Pt(8)
    pf.line_spacing = 1.0
    pf.left_indent = Cm(0.4)
    pf.right_indent = Cm(0.3)
    pf.keep_together = True
    shade(p)
    borders(p)
    mono_font(p.add_run(text), 9)


def add_caption(text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run(text)
    r.italic = True
    r.font.size = Pt(9.5)
    r.font.color.rgb = RGBColor(0x59, 0x59, 0x59)


def add_shot(label):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(2)
    p.paragraph_format.left_indent = Cm(1.2)
    p.paragraph_format.right_indent = Cm(1.2)
    borders(p, color="999999", sz="8", style="dashed")
    shade(p, "FAFAFA")
    p.add_run("\n\n\n\n")
    r = p.add_run(label)
    r.italic = True
    r.font.size = Pt(9.5)
    r.font.color.rgb = RGBColor(0x80, 0x80, 0x80)
    p.add_run("\n\n\n\n")


def add_table(header, rows, opt=None):
    opt = opt or {}
    widths = opt.get("widths")
    mono = opt.get("mono") or set()
    size = opt.get("size", 10)
    t = doc.add_table(rows=1, cols=len(header))
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, text in enumerate(header):
        cell = t.rows[0].cells[i]
        cell.text = ""
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(2)
        p.paragraph_format.space_after = Pt(2)
        r = p.add_run(text)
        r.bold = True
        r.font.size = Pt(size)
        tcPr = cell._tc.get_or_add_tcPr()
        shd = OxmlElement("w:shd")
        shd.set(qn("w:val"), "clear")
        shd.set(qn("w:fill"), "E7E6E6")
        tcPr.append(shd)
    for row in rows:
        cells = t.add_row().cells
        for i, text in enumerate(row):
            cells[i].text = ""
            p = cells[i].paragraphs[0]
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(2)
            p.paragraph_format.line_spacing = 1.1
            r = p.add_run(str(text))
            r.font.size = Pt(size)
            if i in mono:
                mono_font(r, 9)
            if i == 0:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    if widths:
        for r in t.rows:
            for i, w in enumerate(widths):
                r.cells[i].width = Cm(w)
    doc.add_paragraph().paragraph_format.space_after = Pt(2)


# ------------------------------------------------------------------ COVER
c = konten.COVER
for _ in range(2):
    doc.add_paragraph()

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(c["judul"]); r.bold = True; r.font.size = Pt(20)

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(c["subjudul"]); r.bold = True; r.font.size = Pt(16)

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
p.paragraph_format.space_before = Pt(8)
r = p.add_run(c["sub"]); r.italic = True; r.font.size = Pt(10.5)

for _ in range(4):
    doc.add_paragraph()

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Disusun oleh:"); r.font.size = Pt(12)

t = doc.add_table(rows=3, cols=2)
t.alignment = WD_TABLE_ALIGNMENT.CENTER
for i, (label, value) in enumerate(c["identitas"]):
    c0, c1 = t.rows[i].cells
    c0.width, c1.width = Cm(3.0), Cm(7.5)
    p0 = c0.paragraphs[0]; p0.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    r0 = p0.add_run(label); r0.font.size = Pt(12)
    p1 = c1.paragraphs[0]
    r1 = p1.add_run(": " + value); r1.font.size = Pt(12); r1.bold = True

for _ in range(4):
    doc.add_paragraph()

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(c["meta"]); r.font.size = Pt(11.5)

for _ in range(3):
    doc.add_paragraph()

p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(c["prodi"]); r.bold = True; r.font.size = Pt(12)
p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run(c["tahun"]); r.bold = True; r.font.size = Pt(12)

doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)

# ------------------------------------------------------------------ ISI
num_counter = 0
for blok in konten.BLOCKS:
    jenis = blok[0]
    if jenis != "num":
        num_counter = 0
    if jenis == "h1":
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(12)
        p.paragraph_format.space_after = Pt(8)
        p.paragraph_format.keep_with_next = True
        r = p.add_run(blok[1]); r.bold = True; r.font.size = Pt(14)
        pPr = p._p.get_or_add_pPr()
        pBdr = OxmlElement("w:pBdr")
        bottom = OxmlElement("w:bottom")
        bottom.set(qn("w:val"), "single")
        bottom.set(qn("w:sz"), "8")
        bottom.set(qn("w:space"), "2")
        bottom.set(qn("w:color"), "404040")
        pBdr.append(bottom)
        pPr.append(pBdr)
    elif jenis == "h2":
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        r = p.add_run(blok[1]); r.bold = True; r.font.size = Pt(12)
    elif jenis == "p":
        add_para(blok[1], blok[2] if len(blok) > 2 else None)
    elif jenis == "li":
        add_list(blok[1], blok[2] if len(blok) > 2 else None, numbered=False)
    elif jenis == "num":
        num_counter += 1
        add_list(blok[1], blok[2] if len(blok) > 2 else None, numbered=True, n=num_counter)
    elif jenis == "code":
        add_code(blok[1])
    elif jenis == "caption":
        add_caption(blok[1])
    elif jenis == "shot":
        add_shot(blok[1])
    elif jenis == "table":
        add_table(blok[1], blok[2], blok[3] if len(blok) > 3 else None)
    elif jenis == "pagebreak":
        doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)

doc.save(DOCX)
print("DOCX OK ->", DOCX)
