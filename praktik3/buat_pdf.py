# -*- coding: utf-8 -*-
"""Render laporan Praktikum 3 ke PDF dari konten.py (tanpa Word)."""

import os
from xml.sax.saxutils import escape

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_JUSTIFY, TA_CENTER, TA_LEFT
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph,
                                Spacer, Table, TableStyle, PageBreak,
                                KeepTogether, Preformatted)
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.platypus.flowables import HRFlowable

import konten

OUT_DIR = os.path.dirname(os.path.abspath(__file__))
PDF = os.path.join(OUT_DIR, "Praktik3_NAMA_NPM_Ganjil.pdf")

PAGE_W, PAGE_H = A4
LM, RM, TM, BM = 3.0 * cm, 2.5 * cm, 2.5 * cm, 2.5 * cm
CONTENT_W = PAGE_W - LM - RM

BLACK = colors.HexColor("#1A1A1A")
GREY = colors.HexColor("#595959")
GRID = colors.HexColor("#8C8C8C")

body = ParagraphStyle("body", fontName="Times-Roman", fontSize=11.5, leading=15.5,
                      alignment=TA_JUSTIFY, textColor=BLACK, spaceAfter=6)
body_indent = ParagraphStyle("body_indent", parent=body, firstLineIndent=1.0 * cm)
body_bold = ParagraphStyle("body_bold", parent=body, fontName="Times-Bold", spaceAfter=3,
                           spaceBefore=4, alignment=TA_LEFT)
body_small = ParagraphStyle("body_small", parent=body, fontSize=10.5, leading=14)
h1 = ParagraphStyle("h1", fontName="Times-Bold", fontSize=14, leading=17,
                    spaceBefore=10, spaceAfter=4, textColor=BLACK, keepWithNext=1)
h2 = ParagraphStyle("h2", fontName="Times-Bold", fontSize=12, leading=15,
                    spaceBefore=9, spaceAfter=3, textColor=BLACK, keepWithNext=1)
bullet_st = ParagraphStyle("bullet", parent=body, leftIndent=0.75 * cm,
                           bulletIndent=0.25 * cm, spaceAfter=3, leading=14.5)
num_st = ParagraphStyle("num", parent=body, leftIndent=0.85 * cm,
                        firstLineIndent=-0.6 * cm, spaceAfter=3, leading=14.5)
cap_st = ParagraphStyle("cap", fontName="Times-Italic", fontSize=9.5, leading=12,
                        alignment=TA_CENTER, textColor=GREY, spaceBefore=2, spaceAfter=9)
code_st = ParagraphStyle("code", fontName="Courier", fontSize=7.9, leading=10.2,
                         textColor=BLACK)
cell_st = ParagraphStyle("cell", fontName="Times-Roman", fontSize=9.5, leading=12.2,
                         textColor=BLACK)
cell_st_m = ParagraphStyle("cellm", fontName="Courier", fontSize=9, leading=12.2,
                           textColor=BLACK)
head_st = ParagraphStyle("head", fontName="Times-Bold", fontSize=9.8, leading=12.2,
                         alignment=TA_CENTER, textColor=BLACK)


def P(text, style=body):
    return Paragraph(escape(text), style)


def numbered_flowables(items):
    out = []
    for i, (text, opt) in enumerate(items, 1):
        prefix = "%d.  " % i
        content = prefix + text
        out.append(Paragraph(escape(content), num_st))
    return out


def code_block(text):
    pre = Preformatted(escape(text), code_st)
    t = Table([[pre]], colWidths=[CONTENT_W - 0.6 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#F4F4F4")),
        ("BOX", (0, 0), (-1, -1), 0.5, colors.HexColor("#A6A6A6")),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return [Spacer(1, 3), t, Spacer(1, 8)]


def shot_box(label):
    inner = [Spacer(1, 3.2 * cm),
             Paragraph(escape(label), ParagraphStyle("shot", parent=cap_st, spaceAfter=0)),
             Spacer(1, 3.2 * cm)]
    t = Table([[inner]], colWidths=[CONTENT_W - 2.4 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FAFAFA")),
        ("BOX", (0, 0), (-1, -1), 0.7, colors.HexColor("#999999")),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ]))
    wrapper = Table([[t]], colWidths=[CONTENT_W])
    wrapper.setStyle(TableStyle([
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
    ]))
    return [Spacer(1, 5), wrapper]


def data_table(header, rows, widths, mono_cols):
    data = [[Paragraph(escape(h), head_st) for h in header]]
    for row in rows:
        line = []
        for i, v in enumerate(row):
            st = cell_st_m if i in mono_cols else cell_st
            line.append(Paragraph(escape(str(v)), st))
        data.append(line)
    t = Table(data, colWidths=[w * cm for w in widths], repeatRows=1)
    t.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.5, GRID),
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#E7E6E6")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ALIGN", (0, 0), (0, -1), "CENTER"),
        ("ALIGN", (0, 0), (-1, 0), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
    ]))
    return [t, Spacer(1, 8)]


def decorate(canvas, doc):
    canvas.saveState()
    if doc.page > 1:
        canvas.setFont("Times-Roman", 8.5)
        canvas.setFillColor(colors.HexColor("#7F7F7F"))
        canvas.drawRightString(PAGE_W - RM, PAGE_H - TM + 14,
                               "Praktikum 3 - Instruksi Aritmatika dan Logika (lanjutan)")
        canvas.setStrokeColor(colors.HexColor("#BFBFBF"))
        canvas.setLineWidth(0.4)
        canvas.line(LM, PAGE_H - TM + 8, PAGE_W - RM, PAGE_H - TM + 8)
    canvas.setFont("Times-Roman", 9)
    canvas.setFillColor(colors.HexColor("#404040"))
    canvas.drawCentredString(PAGE_W / 2, 1.35 * cm, str(doc.page))
    canvas.restoreState()


def cover_story():
    c = konten.COVER
    s = []
    s.append(Spacer(1, 2.2 * cm))
    s.append(Paragraph(c["judul"], ParagraphStyle(
        "cv1", fontName="Times-Bold", fontSize=21, leading=25, alignment=TA_CENTER)))
    s.append(Spacer(1, 6))
    for line in c["subjudul"].split("\n"):
        s.append(Paragraph(line, ParagraphStyle(
            "cv2", fontName="Times-Bold", fontSize=16.5, leading=21, alignment=TA_CENTER)))
    s.append(Spacer(1, 10))
    s.append(Paragraph(c["sub"], ParagraphStyle(
        "cv3", fontName="Times-Italic", fontSize=10.5, leading=14, alignment=TA_CENTER)))
    s.append(Spacer(1, 3.4 * cm))
    s.append(Paragraph("Disusun oleh:", ParagraphStyle(
        "cv4", fontName="Times-Roman", fontSize=12, leading=16, alignment=TA_CENTER)))
    s.append(Spacer(1, 8))

    ident = [[Paragraph(k, ParagraphStyle("idk", fontName="Times-Roman", fontSize=12,
                                          alignment=2, leading=18)),
              Paragraph(": " + escape(v), ParagraphStyle("idv", fontName="Times-Bold",
                                                         fontSize=12, leading=18))]
             for k, v in c["identitas"]]
    t = Table(ident, colWidths=[3.0 * cm, 7.5 * cm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 2),
        ("RIGHTPADDING", (0, 0), (-1, -1), 2),
        ("TOPPADDING", (0, 0), (-1, -1), 1),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 1),
    ]))
    s.append(t)
    s.append(Spacer(1, 3.2 * cm))
    for line in c["meta"].split("\n"):
        s.append(Paragraph(line, ParagraphStyle("cv5", fontName="Times-Roman", fontSize=11.5,
                                                 leading=16, alignment=TA_CENTER)))
    s.append(Spacer(1, 3.2 * cm))
    s.append(Paragraph(c["prodi"], ParagraphStyle("cv6", fontName="Times-Bold", fontSize=12,
                                                  leading=16, alignment=TA_CENTER)))
    s.append(Paragraph(c["tahun"], ParagraphStyle("cv7", fontName="Times-Bold", fontSize=12,
                                                  leading=16, alignment=TA_CENTER)))
    s.append(PageBreak())
    return s


def build_story():
    s = cover_story()
    pending_nums = []

    def flush_nums():
        if pending_nums:
            s.extend(numbered_flowables(pending_nums))
            del pending_nums[:]

    for blok in konten.BLOCKS:
        jenis = blok[0]
        if jenis != "num":
            flush_nums()

        if jenis == "h1":
            s.append(Paragraph(escape(blok[1]), h1))
            s.append(HRFlowable(width="100%", thickness=0.9, color=colors.HexColor("#404040"),
                                spaceBefore=1, spaceAfter=7))
        elif jenis == "h2":
            s.append(Paragraph(escape(blok[1]), h2))
        elif jenis == "p":
            opt = blok[2] if len(blok) > 2 else {}
            if opt.get("bold"):
                s.append(Paragraph(escape(blok[1]), body_bold))
            elif opt.get("size") == 10.5:
                s.append(Paragraph(escape(blok[1]), body_small))
            elif opt.get("indent"):
                s.append(Paragraph(escape(blok[1]), body_indent))
            else:
                s.append(Paragraph(escape(blok[1]), body))
        elif jenis == "li":
            opt = blok[2] if len(blok) > 2 else {}
            prefix = opt.get("prefix", "")
            text = ("<b>%s</b>" % escape(prefix) if prefix else "") + escape(blok[1])
            s.append(Paragraph(text, bullet_st, bulletText="\u2022"))
        elif jenis == "num":
            pending_nums.append((blok[1], blok[2] if len(blok) > 2 else {}))
        elif jenis == "code":
            s.extend(code_block(blok[1]))
        elif jenis == "caption":
            s.append(Paragraph(escape(blok[1]), cap_st))
        elif jenis == "shot":
            s.extend(shot_box(blok[1]))
        elif jenis == "table":
            opt = blok[3] if len(blok) > 3 else {}
            s.extend(data_table(blok[1], blok[2], opt.get("widths"),
                                opt.get("mono") or set()))
        elif jenis == "pagebreak":
            s.append(PageBreak())

    flush_nums()
    return s


doc = BaseDocTemplate(PDF, pagesize=A4, pageCompression=0,
                      leftMargin=LM, rightMargin=RM, topMargin=TM, bottomMargin=BM,
                      title="Praktikum 3 - Instruksi Aritmatika dan Logika (lanjutan)",
                      author=konten.NAMA)
frame = Frame(LM, BM, CONTENT_W, PAGE_H - TM - BM, id="normal",
              leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
doc.addPageTemplates([PageTemplate(id="main", frames=[frame], onPage=decorate)])
doc.build(build_story())
print("PDF OK ->", PDF)
