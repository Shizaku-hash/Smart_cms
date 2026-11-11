"""Pydantic schemas for Python FastAPI analytics & export APIs."""

from typing import Any, Literal
from pydantic import BaseModel, Field


class FinancialOverviewRequest(BaseModel):
    company_id: int = 1
    date_from: str | None = None
    date_to: str | None = None


class SalaryPreviewRequest(BaseModel):
    payment_type: Literal["fixed", "hourly", "daily", "project", "task"] = "fixed"
    base_salary: float = Field(default=0.0, ge=0.0)
    working_days: float = Field(default=22.0, gt=0.0)
    worked_days: float = Field(default=22.0, ge=0.0)
    working_hours: float = Field(default=176.0, ge=0.0)
    hourly_rate: float = Field(default=0.0, ge=0.0)
    daily_rate: float = Field(default=0.0, ge=0.0)
    overtime_hours: float = Field(default=0.0, ge=0.0)
    overtime_rate: float = Field(default=0.0, ge=0.0)
    task_bonus: float = Field(default=0.0, ge=0.0)
    performance_bonus: float = Field(default=0.0, ge=0.0)
    other_bonuses: float = Field(default=0.0, ge=0.0)
    authorized_deductions: float = Field(default=0.0, ge=0.0)
    unconfirmed_deductions: float = Field(default=0.0, ge=0.0)
    adjustments: float = 0.0


class PerformanceScoreRequest(BaseModel):
    tasks_assigned: int = Field(default=0, ge=0)
    tasks_completed: int = Field(default=0, ge=0)
    tasks_on_time: int = Field(default=0, ge=0)
    attendance_rate: float = Field(default=100.0, ge=0.0, le=100.0)
    manager_rating: float = Field(default=85.0, ge=0.0, le=100.0)
    base_salary: float = Field(default=0.0, ge=0.0)
    weights: dict[str, float] = Field(
        default_factory=lambda: {
            "task_completion": 40.0,
            "on_time": 25.0,
            "attendance": 15.0,
            "manager_rating": 20.0,
        }
    )
    gold_min_score: float = 90.0
    gold_bonus_percent: float = 15.0
    silver_min_score: float = 75.0
    silver_bonus_percent: float = 8.0


class ReportExportPayload(BaseModel):
    title: str
    subtitle: str = ""
    company_name: str = "NovaTech Solutions"
    columns: list[str]
    rows: list[list[Any]]
    totals_row: list[Any] | None = None
    notes: str = ""
    currency_code: str = "USD"
    export_format: Literal["xlsx", "pdf"] = "xlsx"
