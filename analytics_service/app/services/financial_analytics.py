"""Pandas-powered financial analytics, forecasting, and anomaly detection."""

from datetime import datetime
from typing import Any
import numpy as np
import pandas as pd
from ..database import fetch_dataframe


def compute_financial_overview(company_id: int, date_from: str | None = None, date_to: str | None = None) -> dict[str, Any]:
    sql = """
        SELECT t.id, t.transaction_code, t.type, t.base_amount, t.original_amount,
               t.currency_code, t.transaction_date, t.status, t.payment_method,
               COALESCE(ic.name, ec.name, 'Uncategorized') AS category_name,
               d.name AS department_name
        FROM transactions t
        LEFT JOIN income_categories ic ON ic.id = t.income_category_id
        LEFT JOIN expense_categories ec ON ec.id = t.expense_category_id
        LEFT JOIN departments d ON d.id = t.department_id
        WHERE t.company_id = %s AND t.deleted_at IS NULL AND t.status != 'cancelled'
    """
    params: list[Any] = [company_id]
    if date_from:
        sql += " AND t.transaction_date >= %s"
        params.append(date_from)
    if date_to:
        sql += " AND t.transaction_date <= %s"
        params.append(date_to)

    df = fetch_dataframe(sql, tuple(params))
    if df.empty:
        return {
            "total_income": 0.0,
            "total_expenses": 0.0,
            "net_profit": 0.0,
            "profit_margin_percent": 0.0,
            "monthly_series": [],
            "forecast_next_3_months": [],
            "expense_by_category": [],
            "currency_breakdown": [],
            "anomalies": [],
        }

    df["base_amount"] = df["base_amount"].astype(float)
    df["original_amount"] = df["original_amount"].astype(float)
    df["dt"] = pd.to_datetime(df["transaction_date"])
    df["month"] = df["dt"].dt.strftime("%Y-%m")

    completed = df[df["status"].isin(["completed", "approved"])]
    total_income = float(completed.loc[completed["type"] == "income", "base_amount"].sum())
    total_expenses = float(completed.loc[completed["type"] == "expense", "base_amount"].sum())
    net_profit = round(total_income - total_expenses, 2)
    margin = round((net_profit / total_income * 100.0), 2) if total_income > 0 else 0.0

    # Monthly series
    monthly_pivot = (
        completed.groupby(["month", "type"])["base_amount"]
        .sum()
        .unstack(fill_value=0.0)
        .reset_index()
        .sort_values("month")
    )
    if "income" not in monthly_pivot.columns:
        monthly_pivot["income"] = 0.0
    if "expense" not in monthly_pivot.columns:
        monthly_pivot["expense"] = 0.0
    monthly_pivot["profit"] = monthly_pivot["income"] - monthly_pivot["expense"]

    monthly_series = [
        {
            "month": str(row["month"]),
            "income": round(float(row["income"]), 2),
            "expense": round(float(row["expense"]), 2),
            "profit": round(float(row["profit"]), 2),
        }
        for _, row in monthly_pivot.iterrows()
    ]

    # Simple linear trend forecast for next 3 months
    forecast: list[dict[str, Any]] = []
    if len(monthly_series) >= 2:
        x = np.arange(len(monthly_series), dtype=float)
        y_inc = np.array([m["income"] for m in monthly_series], dtype=float)
        y_exp = np.array([m["expense"] for m in monthly_series], dtype=float)
        inc_slope, inc_intercept = np.polyfit(x, y_inc, 1)
        exp_slope, exp_intercept = np.polyfit(x, y_exp, 1)

        last_month_dt = datetime.strptime(monthly_series[-1]["month"] + "-01", "%Y-%m-%d")
        for step in range(1, 4):
            idx = len(monthly_series) - 1 + step
            next_month_dt = (pd.Timestamp(last_month_dt) + pd.DateOffset(months=step)).to_pydatetime()
            proj_inc = max(0.0, round(float(inc_slope * idx + inc_intercept), 2))
            proj_exp = max(0.0, round(float(exp_slope * idx + exp_intercept), 2))
            forecast.append({
                "month": next_month_dt.strftime("%Y-%m"),
                "projected_income": proj_inc,
                "projected_expense": proj_exp,
                "projected_profit": round(proj_inc - proj_exp, 2),
            })

    # Expense by category
    exp_df = completed[completed["type"] == "expense"]
    exp_cats = []
    if not exp_df.empty:
        cat_grp = exp_df.groupby("category_name")["base_amount"].sum().sort_values(ascending=False)
        exp_cats = [{"category": str(k), "amount": round(float(v), 2)} for k, v in cat_grp.items()]

    # Currency exposure breakdown
    curr_grp = completed.groupby(["currency_code", "type"])["original_amount"].sum().reset_index()
    currency_breakdown = [
        {
            "currency": str(r["currency_code"]),
            "type": str(r["type"]),
            "original_amount": round(float(r["original_amount"]), 2),
        }
        for _, r in curr_grp.iterrows()
    ]

    # Statistical anomaly detection on expenses (mean + 1.5 * std)
    anomalies: list[dict[str, Any]] = []
    if len(exp_df) >= 4:
        mean_val = float(exp_df["base_amount"].mean())
        std_val = float(exp_df["base_amount"].std(ddof=0))
        threshold = mean_val + 1.5 * std_val
        outliers = exp_df[exp_df["base_amount"] > threshold].sort_values("base_amount", ascending=False).head(5)
        for _, r in outliers.iterrows():
            anomalies.append({
                "transaction_code": str(r["transaction_code"]),
                "category": str(r["category_name"]),
                "amount": round(float(r["base_amount"]), 2),
                "date": str(r["transaction_date"]),
                "threshold": round(threshold, 2),
            })

    return {
        "total_income": round(total_income, 2),
        "total_expenses": round(total_expenses, 2),
        "net_profit": net_profit,
        "profit_margin_percent": margin,
        "monthly_series": monthly_series,
        "forecast_next_3_months": forecast,
        "expense_by_category": exp_cats,
        "currency_breakdown": currency_breakdown,
        "anomalies": anomalies,
    }
