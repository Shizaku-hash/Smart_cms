"""Pytest suite for Python FastAPI analytics, calculations, and OpenPyXL/ReportLab exports."""

from io import BytesIO
from fastapi.testclient import TestClient
from openpyxl import load_workbook
from app.config import settings
from app.main import app

client = TestClient(app)
HEADERS = {"X-API-Key": settings.API_KEY}


def test_health_endpoint() -> None:
    res = client.get("/health")
    assert res.status_code == 200
    assert res.json()["status"] == "ok"


def test_auth_required() -> None:
    res = client.get("/api/v1/analytics/financial")
    assert res.status_code == 401


def test_financial_analytics_from_mysql() -> None:
    res = client.get("/api/v1/analytics/financial?company_id=1", headers=HEADERS)
    assert res.status_code == 200
    data = res.json()
    assert data["total_income"] > 0
    assert data["total_expenses"] > 0
    assert abs(data["net_profit"] - (data["total_income"] - data["total_expenses"])) < 0.05
    assert len(data["monthly_series"]) >= 6
    assert len(data["forecast_next_3_months"]) == 3


def test_salary_calculation_prompt_scenario() -> None:
    """Base $500 + Tasks $100 + Performance $50 + Overtime $30 - Authorized Deductions $20 = $660;
    Unconfirmed deductions must NEVER be applied."""
    payload = {
        "payment_type": "fixed",
        "base_salary": 500,
        "working_days": 22,
        "worked_days": 22,
        "overtime_hours": 2,
        "overtime_rate": 15,
        "task_bonus": 100,
        "performance_bonus": 50,
        "authorized_deductions": 20,
        "unconfirmed_deductions": 99,
    }
    res = client.post("/api/v1/analytics/payroll/preview", json=payload, headers=HEADERS)
    assert res.status_code == 200
    data = res.json()
    assert data["net_salary"] == 660.0
    assert data["ignored_unconfirmed_deductions"] == 99.0


def test_configurable_performance_evaluation() -> None:
    payload = {
        "tasks_assigned": 10,
        "tasks_completed": 9,
        "tasks_on_time": 9,
        "attendance_rate": 96.0,
        "manager_rating": 92.0,
        "base_salary": 2000.0,
        "weights": {"task_completion": 40, "on_time": 25, "attendance": 15, "manager_rating": 20},
        "gold_min_score": 90.0,
        "gold_bonus_percent": 15.0,
    }
    res = client.post("/api/v1/analytics/performance/evaluate", json=payload, headers=HEADERS)
    assert res.status_code == 200
    data = res.json()
    assert data["computed_score"] >= 90.0
    assert data["tier"] == "gold"
    assert data["recommended_bonus"] == 300.0


def test_python_excel_and_pdf_exports() -> None:
    payload = {
        "title": "Test Financial Report",
        "subtitle": "Automated Pytest Export",
        "company_name": "NovaTech Solutions",
        "columns": ["Code", "Category", "Amount"],
        "rows": [["INC-001", "Client Retainers", 1500.0], ["EXP-001", "Office rent", 500.0]],
        "totals_row": ["Total", "", 1000.0],
        "notes": "Verified by Python OpenPyXL & ReportLab",
        "export_format": "xlsx",
    }
    res_xlsx = client.post("/api/v1/reports/export", json=payload, headers=HEADERS)
    assert res_xlsx.status_code == 200
    wb = load_workbook(BytesIO(res_xlsx.content))
    ws = wb.active
    assert "NovaTech Solutions" in str(ws["A1"].value)
    assert ws["A5"].value == "INC-001"

    payload["export_format"] = "pdf"
    res_pdf = client.post("/api/v1/reports/export", json=payload, headers=HEADERS)
    assert res_pdf.status_code == 200
    assert res_pdf.content.startswith(b"%PDF")
