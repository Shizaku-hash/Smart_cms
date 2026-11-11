"""Analytics API endpoints for financial, payroll, and performance processing."""

from fastapi import APIRouter, Query
from ..schemas import PerformanceScoreRequest, SalaryPreviewRequest
from ..services.financial_analytics import compute_financial_overview
from ..services.payroll_analytics import compute_payroll_distribution, compute_salary_preview
from ..services.performance_analytics import evaluate_performance, fetch_performance_summary

router = APIRouter(prefix="/api/v1/analytics", tags=["Analytics"])


@router.get("/financial")
def get_financial_analytics(
    company_id: int = Query(default=1),
    date_from: str | None = Query(default=None),
    date_to: str | None = Query(default=None),
) -> dict:
    return compute_financial_overview(company_id=company_id, date_from=date_from, date_to=date_to)


@router.get("/payroll")
def get_payroll_analytics(company_id: int = Query(default=1)) -> dict:
    return compute_payroll_distribution(company_id=company_id)


@router.post("/payroll/preview")
def post_salary_preview(payload: SalaryPreviewRequest) -> dict:
    return compute_salary_preview(payload)


@router.get("/performance")
def get_performance_summary(company_id: int = Query(default=1)) -> dict:
    return {"items": fetch_performance_summary(company_id=company_id)}


@router.post("/performance/evaluate")
def post_performance_evaluate(payload: PerformanceScoreRequest) -> dict:
    return evaluate_performance(payload)
