"""Python-powered XLSX (OpenPyXL) and PDF (ReportLab) export endpoints."""

from fastapi import APIRouter
from fastapi.responses import Response
from ..schemas import ReportExportPayload
from ..services.report_exporter import generate_excel_bytes, generate_pdf_bytes

router = APIRouter(prefix="/api/v1/reports", tags=["Reports"])


@router.post("/export")
def export_report(payload: ReportExportPayload) -> Response:
    if payload.export_format == "pdf":
        data = generate_pdf_bytes(payload)
        return Response(
            content=data,
            media_type="application/pdf",
            headers={"Content-Disposition": 'attachment; filename="report.pdf"'},
        )

    data = generate_excel_bytes(payload)
    return Response(
        content=data,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": 'attachment; filename="report.xlsx"'},
    )
