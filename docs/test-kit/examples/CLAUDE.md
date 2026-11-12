# CLAUDE.md

Project rules are in AGENTS.md, which Claude Code loads on its own. This file only holds what is
specific to running Claude in this project's dev container.

- Packagist is blocked by the container firewall: ask the user to run `composer require/update`
  (they can type `! composer ...` in the prompt), then review the recipe changes.
- Never experiment on the dev database: the user works with its data. Prefer `php bin/phpunit`
  (its own `tcu_test` database) to check pages; for anything else, use a throwaway copy
  (`CREATE DATABASE <name> TEMPLATE tcu`) and drop it afterwards.
