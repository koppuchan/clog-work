#!/bin/bash

INPUT=$(cat)

STOP_HOOK_ACTIVE=$(echo "$INPUT" | jq -r '.stop_hook_active // false')

# ルールに沿った修正後に無限ループを起こさせないため
if [[ "$STOP_HOOK_ACTIVE" == "true" ]]; then
    exit 0
fi

FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

MESSAGES=()

# ファイルパスが**.phpのものを操作後、コーディング規約とセキュリティガイドを確認するように指示
if [[ "$FILE_PATH" =~ \.php$ ]]; then
    MESSAGES+=(" ./docs/backend/CODING_GUIDE.md に準拠しているか確認実行")
    MESSAGES+=(" ./docs/backend/security.md に準拠しているか確認実行")
    MESSAGES+=(" ./docs/tables/README.md のテーブル設計に準拠しているか確認実行")
fi

# ファイルパスが*/Controllers/**.phpのものを操作後、Controllerガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Controllers/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/controller.md に準拠しているか確認実行")
fi

# ファイルパスが**/Models/*.phpまたはdatabase/**.phpファイルを操作後、モデルガイドとテーブル設計を確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Models/[^/]+\.php$ || "$FILE_PATH" =~ ^database/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/model.md に準拠しているか確認実行")
    MESSAGES+=(" ./docs/tables/README.md のテーブル設計に準拠しているか確認実行")
fi

# ファイルパスが**/Repositories/*.phpのものを操作後、Repositoryガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Repositories/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/repository.md に準拠しているか確認実行")
fi

# ファイルパスが**/Services/*.phpのものを操作後、Serviceガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Services/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/service.md に準拠しているか確認実行")
fi

# ファイルパスが**/Policies/*.phpのものを操作後、Policyガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Policies/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/policy.md に準拠しているか確認実行")
fi

# ファイルパスが**/Enums/*.phpのものを操作後、Enumガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^.*/Enums/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/enum.md に準拠しているか確認実行")
fi

# ファイルパスが**/tests/**.phpのものを操作後、Testingガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^tests/.*\.php$ ]]; then
    MESSAGES+=(" ./docs/backend/testing.md に準拠しているか確認実行")
fi

# ファイルパスがresources/js/**.tsxまたは**.tsのものを操作後、フロントエンドガイドを確認するように指示
if [[ "$FILE_PATH" =~ ^resources/js/.*\.(tsx|ts)$ ]]; then
    MESSAGES+=(" ./docs/frontend/CODING_GUIDE.md に準拠しているか確認実行")
fi

# MESSAGESに命令文がある場合に実行
if [ ${#MESSAGES[@]} -gt 0 ]; then
    HEADER="⚠️ 以下のガイドラインを確認してください："

    FORMATTED=$(for i in "${!MESSAGES[@]}"; do
        INDEX=$((i + 1))
        echo "$INDEX. ${MESSAGES[$i]}"
    done)

    FULL_MESSAGE="$HEADER"$'\n'"$FORMATTED"
    ESCAPED_REASON=$(jq -Rs . <<< "$FULL_MESSAGE")

    cat <<EOF
{
    "decision": "allow",
    "message": $ESCAPED_REASON
}
EOF

    exit 0
fi

exit 0

