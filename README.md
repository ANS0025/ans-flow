## 概要
Ans Flowは、独自のGit Branch戦略に沿ったワークフローをサポートするためにGit Flowを独自にカスタマイズしたCLIツール。  
Featureブランチ、Releaseブランチ、バージョン管理等、Git Flowの一般的なタスクをより簡略化したコマンドを提供。  
Ans Flowは、SymfonyConsoleコンポーネントを使用して構築されている。  

## プロジェクト目的
独自のGit Branch戦略に沿ったGit Flow-likeなツールを開発することで、開発ワークフローの簡易化・効率化を実現することを目的としている。

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
