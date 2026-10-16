# Fail-open by default, JSON-only responses

A Hook Command always answers with JSON on stdout and exit code 0; the package never exits with 2. An exception makes Artisan exit 1, which Claude Code treats as a non-blocking error, so a broken Hook Command lets the action proceed. A Registration can opt into failing closed, which is written as `onFailure: "block"` in the Settings Entry. This keeps Hook Commands testable through captured output, and keeps a misconfigured database from freezing every edit, while still letting a guard the user relies on fail closed.
