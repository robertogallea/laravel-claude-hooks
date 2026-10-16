## Package development
  - Use the glossary in `CONTEXT.md` for names, docblocks and test names. Decisions in `docs/adr` are binding; record a new one there when a choice is hard to reverse.
  - Tickets live in `.scratch/`, as `docs/agents/issue-tracker.md` describes.
  - All of Claude Code's wire format (payload fields, response JSON, hook handler JSON) lives in `src/Wire/ClaudeCode.php` ([ADR 0001](docs/adr/0001-claude-only-with-an-agent-seam.md)); response helpers live on the Hook Inputs. Field names come from the hooks reference, https://code.claude.com/docs/en/hooks: check there before changing one.
  - The README is the only documentation. Document every new or changed feature in it in the same change. Each PHP and JSON sample in it sits under a `<!-- readme-test: <name> -->` comment and is run by `tests/Feature/ReadmeTest.php`: add the comment and a test for every new sample.
  - When the public API changes, update the Boost skill in `resources/boost/skills/laravel-claude-hooks-writing-hooks/SKILL.md` in the same change.
  - Add every user-visible change to `CHANGELOG.md` in the same change, under `## [Unreleased]`, in its Keep a Changelog group (Added, Changed, Deprecated, Removed, Fixed, Security), one line per change. Mark a breaking change **Breaking:** with how to upgrade.
  - On a release, rename `## [Unreleased]` to `## [x.y.z] - YYYY-MM-DD`, start a new empty `## [Unreleased]` above it, and update the links at the bottom.

## Commands
  - Test: `composer test`
  - Linting: `composer lint` (`composer format` to fix)
  - Static analysis: `composer analyse`
