"""Configuration loader for SmartCMS Python FastAPI analytics service."""

import os
from pathlib import Path
from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parents[2]
ENV_PATH = BASE_DIR / ".env"
if ENV_PATH.exists():
    load_dotenv(ENV_PATH)


class Settings:
    DB_HOST: str = os.getenv("DB_HOST", "127.0.0.1")
    DB_PORT: int = int(os.getenv("DB_PORT", "3306"))
    DB_DATABASE: str = os.getenv("DB_DATABASE", "smart_cms")
    DB_USERNAME: str = os.getenv("DB_USERNAME", "cms_user")
    DB_PASSWORD: str = os.getenv("DB_PASSWORD", "cms_dev_pass")
    API_KEY: str = os.getenv("PYTHON_API_KEY", "smart_cms_internal_analytics_key_2026")


settings = Settings()
