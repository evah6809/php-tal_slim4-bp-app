# git 操作

git clone <リポジトリのURL>
リモートにあるリポジトリを丸ごと手元にダウンロードし、ローカルリポジトリを新しく作成する場合に使います（最初の一回）。

git fetch
すでにローカルにGitリポジトリが存在している状態で、リモートリポジトリの最新の変更履歴（コミットなど）だけを手元に同期する場合に使います。
ローカルにGitリポジトリが存在しない時は、
git init
git remote add origin <リモートリポジトリのURL>
git fetch origin
git marge origin/main

git status で現在のブランチが main であることを確認し、
git diff main origin/main で、ローカルの main とリモートの origin/main の違い（差分）を確認できます。
1.現在のブランチ名を確認する:git branch。以下のコマンドを実行し、ローカルに存在するブランチ名を確認します。
git branch -a

# docker 起動

docker file のあるディレクトリで
docker compose up -d

終了はdown
docker compose down


