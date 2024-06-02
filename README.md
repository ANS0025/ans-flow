## 概要
Ans Flowは、独自のGitブランチ戦略に沿ったワークフローをサポートするためにGit FlowをカスタマイズしたCLIツールです。  
Featureブランチ、Releaseブランチ、バージョン管理など、Git Flowの一般的なタスクを簡略化するコマンドを提供します。  
Ans FlowはSymfony Consoleコンポーネントを使用して構築されています。  

## プロジェクト目的
独自のGitブランチ戦略に基づいたGit Flowのようなツールを開発することで、開発ワークフローを簡略化し、効率化することを目的としています。

## コマンド
- Git Flowの初期化: ```ans flow:init```
- 機能ブランチの管理
  - 機能ブランチの一覧表示: ```ans flow:feature [list]```
  - 機能ブランチの作成: ```ans flow:feature start <name>```
  - 機能ブランチの完了: ```ans flow:feature finish [-k] <name>```
- リリースブランチの管理:
  - リリースブランチの一覧表示: ```ans flow:release [list]```
  - リリースブランチの作成: ```ans flow:release start <name>```
  - リリースブランチの完了: ```ans flow:release finish [-pk] <name>```
- バージョン管理: ```ans flow:version```

## 参考資料
- [The Console Component](https://symfony.com/doc/current/components/console.html)
- [Git-flowをざっと整理してみた](https://dev.classmethod.jp/articles/introduce-git-flow/)
- [gitflow](https://github.com/nvie/gitflow)
