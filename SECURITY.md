# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| < 2.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Report vulnerabilities privately via GitHub's private vulnerability reporting:

1. Go to the [Security tab](https://github.com/mwguerra/web-terminal/security) of this repository.
2. Click **Report a vulnerability** and fill in the advisory form.

You can expect an acknowledgement within 72 hours and a status update within 7 days. Please include a description of the issue, steps to reproduce, affected versions, and any known mitigations.

## Scope

This package executes whitelisted commands on local systems or SSH servers from the browser. Access control relies on the command whitelist and its validation layers. Reports are especially welcome for issues in:

- Command whitelist validation and bypasses (`CommandValidator`, wildcard patterns, shell operator handling)
- Command injection via pipes, redirection, chaining, variable expansion, or control characters
- Credential protection for SSH connections and environment variables
- Session management and cross-session isolation (tmux, file, and process session managers)
- Rate limiting and audit logging of blocked command attempts

Hardening guidance for deployments is documented in the [Security by Design](README.md#security-by-design) section of the README.
