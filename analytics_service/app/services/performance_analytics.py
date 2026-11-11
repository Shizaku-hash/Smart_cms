"""Configurable employee performance scoring & bonus tier engine."""

from typing import Any
from ..database import fetch_dataframe
from ..schemas import PerformanceScoreRequest


def evaluate_performance(req: PerformanceScoreRequest) -> dict[str, Any]:
    w = req.weights
    w_task = float(w.get("task_completion", 40.0))
    w_time = float(w.get("on_time", 25.0))
    w_att = float(w.get("attendance", 15.0))
    w_mgr = float(w.get("manager_rating", 20.0))
    total_w = w_task + w_time + w_att + w_mgr
    if total_w <= 0:
        total_w = 100.0

    task_rate = (req.tasks_completed / req.tasks_assigned * 100.0) if req.tasks_assigned > 0 else 100.0
    on_time_rate = (req.tasks_on_time / req.tasks_completed * 100.0) if req.tasks_completed > 0 else 100.0

    score = (
        task_rate * w_task
        + on_time_rate * w_time
        + req.attendance_rate * w_att
        + req.manager_rating * w_mgr
    ) / total_w
    score = round(min(100.0, max(0.0, score)), 2)

    bonus_percent = 0.0
    tier = "standard"
    if score >= req.gold_min_score:
        bonus_percent = req.gold_bonus_percent
        tier = "gold"
    elif score >= req.silver_min_score:
        bonus_percent = req.silver_bonus_percent
        tier = "silver"

    recommended_bonus = round(req.base_salary * (bonus_percent / 100.0), 2)

    return {
        "task_completion_rate": round(task_rate, 2),
        "on_time_rate": round(on_time_rate, 2),
        "attendance_rate": round(req.attendance_rate, 2),
        "manager_rating": round(req.manager_rating, 2),
        "computed_score": score,
        "tier": tier,
        "bonus_percent": bonus_percent,
        "recommended_bonus": recommended_bonus,
    }


def fetch_performance_summary(company_id: int) -> list[dict[str, Any]]:
    sql = """
        SELECT e.full_name, e.employee_code, pr.computed_score, pr.recommended_bonus, pr.period_label
        FROM performance_records pr
        INNER JOIN employees e ON e.id = pr.employee_id
        WHERE pr.company_id = %s AND pr.deleted_at IS NULL
        ORDER BY pr.computed_score DESC
        LIMIT 12
    """
    df = fetch_dataframe(sql, (company_id,))
    if df.empty:
        return []
    return [
        {
            "employee_name": str(r["full_name"]),
            "employee_code": str(r["employee_code"]),
            "score": round(float(r["computed_score"]), 2),
            "bonus": round(float(r["recommended_bonus"]), 2),
            "period": str(r["period_label"]),
        }
        for _, r in df.iterrows()
    ]
