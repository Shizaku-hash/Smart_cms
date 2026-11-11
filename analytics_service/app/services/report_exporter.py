"""Excel (OpenPyXL + Pandas) and PDF (ReportLab) exporter microservice."""

from io import BytesIO
from datetime import datetime
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle
from ..schemas import ReportExportPayload


def generate_excel_bytes(payload: ReportExportPayload) -> bytes:
    wb = Workbook()
    ws = wb.active
    ws.title = "Report"

    col_count = max(1, len(payload.columns))
    last_col = get_column_letter(col_count)

    # Title Banner
    ws.merge_cells(f"A1:{last_col}1")
    ws["A1"] = f"{payload.company_name} — {payload.title}"
    ws["A1"].font = Font(name="Calibri", size=14, bold=True, color="FFFFFF")
    ws["A1"].fill = PatternFill("solid", fgColor="1E293B")
    ws["A1"].alignment = Alignment(horizontal="left", vertical="center", indent=1)
    ws.row_dimensions[1].height = 28

    # Subtitle & Timestamp
    ws.merge_cells(f"A2:{last_col}2")
    sub = payload.subtitle or f"Generated on {datetime.now().strftime('%Y-%m-%d %H:%M')}"
    ws["A2"] = sub
    ws["A2"].font = Font(name="Calibri", size=10, italic=True, color="475569")
    ws["A2"].alignment = Alignment(horizontal="left", vertical="center", indent=1)
    ws.row_dimensions[2].height = 20

    # Header Row
    header_row_idx = 4
    header_fill = PatternFill("solid", fgColor="2563EB")
    header_font = Font(name="Calibri", size=11, bold=True, color="FFFFFF")
    thin_side = Side(style="thin", color="CBD5E1")
    border = Border(left=thin_side, right=thin_side, top=thin_side, bottom=thin_side)

    for idx, col_name in enumerate(payload.columns, start=1):
        cell = ws.cell(row=header_row_idx, column=idx, value=str(col_name))
        cell.fill = header_fill
        cell.font = header_font
        cell.alignment = Alignment(horizontal="center", vertical="center")
        cell.border = border
    ws.row_dimensions[header_row_idx].height = 24

    # Data Rows
    row_idx = header_row_idx + 1
    zebra_fill = PatternFill("solid", fgColor="F8FAFC")
    for r_num, row_data in enumerate(payload.rows):
        for c_num in range(col_count):
            val = row_data[c_num] if c_num < len(row_data) else ""
            cell = ws.cell(row=row_idx, column=c_num + 1, value=val)
            cell.border = border
            cell.font = Font(name="Calibri", size=10)
            if r_num % 2 == 1:
                cell.fill = zebra_fill
        row_idx += 1

    # Totals Row
    if payload.totals_row:
        tot_fill = PatternFill("solid", fgColor="E2E8F0")
        tot_font = Font(name="Calibri", size=10, bold=True)
        for c_num in range(col_count):
            val = payload.totals_row[c_num] if c_num < len(payload.totals_row) else ""
            cell = ws.cell(row=row_idx, column=c_num + 1, value=val)
            cell.fill = tot_fill
            cell.font = tot_font
            cell.border = border
        row_idx += 1

    # Notes
    if payload.notes:
        row_idx += 1
        ws.merge_cells(f"A{row_idx}:{last_col}{row_idx}")
        ws[f"A{row_idx}"] = f"Notes: {payload.notes}"
        ws[f"A{row_idx}"].font = Font(name="Calibri", size=10, italic=True, color="334155")

    # Auto-fit columns
    for col_idx in range(1, col_count + 1):
        col_letter = get_column_letter(col_idx)
        max_len = 12
        for cell in ws[col_letter]:
            if cell.row >= header_row_idx and cell.value is not None:
                max_len = max(max_len, min(42, len(str(cell.value)) + 4))
        ws.column_dimensions[col_letter].width = max_len

    buf = BytesIO()
    wb.save(buf)
    return buf.getvalue()


def generate_pdf_bytes(payload: ReportExportPayload) -> bytes:
    buf = BytesIO()
    page_size = landscape(A4) if len(payload.columns) > 5 else A4
    doc = SimpleDocTemplate(
        buf,
        pagesize=page_size,
        leftMargin=14 * mm,
        rightMargin=14 * mm,
        topMargin=14 * mm,
        bottomMargin=14 * mm,
    )

    styles = getSampleStyleSheet()
    title_style = ParagraphStyle("CmsTitle", parent=styles["Heading1"], fontSize=15, textColor=colors.HexColor("#0F172A"), spaceAfter=4)
    sub_style = ParagraphStyle("CmsSub", parent=styles["Normal"], fontSize=9.5, textColor=colors.HexColor("#475569"), spaceAfter=10)
    cell_style = ParagraphStyle("CmsCell", parent=styles["Normal"], fontSize=8.5, leading=10.5)

    elements = [
        Paragraph(f"<b>{payload.company_name}</b> — {payload.title}", title_style),
        Paragraph(payload.subtitle or f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M')}", sub_style),
    ]

    table_data = [[Paragraph(f"<b>{c}</b>", ParagraphStyle("Hdr", parent=cell_style, textColor=colors.white)) for c in payload.columns]]
    for row in payload.rows:
        padded = [Paragraph(str(row[i] if i < len(row) else ""), cell_style) for i in range(len(payload.columns))]
        table_data.append(padded)

    if payload.totals_row:
        tot_padded = [
            Paragraph(f"<b>{payload.totals_row[i] if i < len(payload.totals_row) else ''}</b>", cell_style)
            for i in range(len(payload.columns))
        ]
        table_data.append(tot_padded)

    avail_width = page_size[0] - 28 * mm
    col_widths = [avail_width / max(1, len(payload.columns))] * max(1, len(payload.columns))

    tbl = Table(table_data, colWidths=col_widths, repeatRows=1)
    tbl_style = [
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#2563EB")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#CBD5E1")),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F8FAFC")]),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]
    if payload.totals_row:
        tbl_style.append(("BACKGROUND", (0, -1), (-1, -1), colors.HexColor("#E2E8F0")))
    tbl.setStyle(TableStyle(tbl_style))
    elements.append(tbl)

    if payload.notes:
        elements.append(Spacer(1, 10))
        elements.append(Paragraph(f"<b>Notes:</b> {payload.notes}", sub_style))

    doc.build(elements)
    return buf.getvalue()
