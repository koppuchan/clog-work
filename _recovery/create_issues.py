#!/usr/bin/env python3
"""GitHub Issue 一括作成

事前準備:
    sudo apt install -y gh
    gh auth login

実行:
    python3 _recovery/create_issues.py            # 実際に作成
    python3 _recovery/create_issues.py --dry-run  # 一覧の確認のみ
"""
import subprocess
import sys
import tempfile
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from issues import ISSUES  # noqa: E402

REPO = "koppuchan/clog-work"
DRY = "--dry-run" in sys.argv


def main():
    if not DRY:
        r = subprocess.run(["gh", "auth", "status"], capture_output=True)
        if r.returncode != 0:
            sys.exit("gh が未認証です。先に `gh auth login` を実行してください。")

    for i, (title, body) in enumerate(ISSUES, 1):
        if DRY:
            print(f"{i:2d}. {title}")
            continue

        with tempfile.NamedTemporaryFile("w", suffix=".md", delete=False,
                                         encoding="utf-8") as f:
            f.write(body)
            path = f.name
        try:
            r = subprocess.run(
                ["gh", "issue", "create", "--repo", REPO,
                 "--title", title, "--body-file", path],
                capture_output=True, text=True,
            )
            if r.returncode == 0:
                print(f"{i:2d}. OK   {title}  {r.stdout.strip()}")
            else:
                print(f"{i:2d}. FAIL {title}\n     {r.stderr.strip()}")
        finally:
            os.unlink(path)

    print(f"\n合計 {len(ISSUES)} 件")


if __name__ == "__main__":
    main()
