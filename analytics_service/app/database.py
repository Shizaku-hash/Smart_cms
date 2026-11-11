"""PyMySQL + Pandas database helper for analytics queries."""

from contextlib import contextmanager
from typing import Any, Iterator
import pandas as pd
import pymysql
from pymysql.cursors import DictCursor
from .config import settings


@contextmanager
def get_connection() -> Iterator[pymysql.connections.Connection]:
    conn = pymysql.connect(
        host=settings.DB_HOST,
        port=settings.DB_PORT,
        user=settings.DB_USERNAME,
        password=settings.DB_PASSWORD,
        database=settings.DB_DATABASE,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=True,
    )
    try:
        yield conn
    finally:
        conn.close()


def fetch_dataframe(sql: str, params: tuple[Any, ...] | dict[str, Any] | None = None) -> pd.DataFrame:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            rows = cur.fetchall()
            if not rows:
                return pd.DataFrame()
            return pd.DataFrame(rows)
