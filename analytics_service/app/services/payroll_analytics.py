"""Payroll distribution & salary calculation verification service."""

from typing import Any
from ..database import fetch_dataframe
from ..schemas import SalaryPreviewRequest


def compute_salary_preview(req: SalaryPreviewRequest) -> dict[str, Any]:
    if req.payment_type == "hourly":
        computed_base = round(req.hourly_rate * req.working_hours, 2)
    elif req.payment_type == "daily":
        computed_base = round(req.daily_rate * req.worked_days, 2)
    else:
        ratio = min(1.0, req.worked_days / req.working_days) if req.working_days > 0 else 1.0
        computed_base = round(req.base_salary * ratio, 2) if req.payment_type == "fixed" and req.worked_days < req.working_days else round(req.base_salary, 2)

    overtime_pay = round(req.overtime_hours * req.overtime_rate, 2)
    gross = round(
        computed_base + overtime_pay + req.task_bonus + req.performance_bonus + req.other_bonuses + req.adjustments,
        2,
    )
    # CRITICAL RULE: unconfirmed_deductions are NEVER subtracted!
    net_salary = round(max(0.0, gross - req.authorized_deductions), 2)

    return {
        "computed_base_salary": computed_base,
        "overtime_pay": overtime_pay,
        "total_bonuses": round(req.task_bonus + req.performance_bonus + req.other_bonuses, 2),
        "gross_salary": gross,
        "authorized_deductions": round(req.authorized_deductions, 2),
        "ignored_unconfirmed_deductions": round(req.unconfirmed_deductions, 2),
        "adjustments": round(req.adjustments, 2),
        "net_salary": net_salary,
    }


def compute_payroll_distribution(company_id: int) -> dict[str, Any]:
    sql = """
        SELECT p.id, p.period_year, p.period_month, p.payment_type,
               p.base_salary, p.net_salary, p.status,
               COALESCE(d.name, 'Unassigned') AS department_name
        FROM payrolls p
        INNER JOIN employees e ON e.id = p.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE p.company_id = %s AND p.deleted_at IS NULL
    """
    df = fetch_dataframe(sql, (company_id,))
    if df.empty:
        return {"by_department": [], "by_payment_type": [], "total_paid": 0.0, "total_pending": 0.0}

    df["net_salary"] = df["net_salary"].astype(float)
    latest_yr = int(df["period_year"].max())
    latest_mo = int(df[df["period_year"] == latest_yr]["period_month"].max())
    latest_df = df[(df["period_year"] == latest_yr) & (df["period_month"] == latest_mo)]

    by_dept = (
        latest_df.groupby("department_name")["net_salary"]
        .sum()
        .sort_values(ascending=False)
        .reset_index()
    )
    by_type = latest_df.groupby("payment_type")["net_salary"].sum().reset_index()

    return {
        "latest_period": f"{latest_yr}-{latest_mo:02d}",
        "by_department": [
            {"department": str(r["department_name"]), "total": round(float(r["net_salary"]), 2)}
            for _, r in by_dept.iterrows()
        ],
        "by_payment_type": [
            {"payment_type": str(r["payment_type"]), "total": round(float(r["net_salary"]), 2)}
            for _, r in by_type.iterrows()
        ],
        "total_paid": round(float(df.loc[df["status"] == "paid", "net_salary"].sum()), 2),
        "total_pending": round(float(df.loc[df["status"] != "paid", "net_salary"].sum()), 2),
    }
