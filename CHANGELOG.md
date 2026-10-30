# Changelog

## Unreleased

### Security
- **SSRF via IP-literal hosts fixed.** URLs whose host was an IP literal (e.g. `https://127.0.0.1/`,
  `https://169.254.169.254/`, `https://[::1]/`) skipped DNS resolution and therefore the private/reserved
  range check. They are now validated by `IpLiteralGuard` before any connection, on the initial request and
  every redirect hop, and rejected with `Dzentota\DnsResolver\SecurityException`. Covered forms: dotted IPv4,
  decimal/octal/hex/short IPv4 (`2130706433`, `0177.0.0.1`, `0x7f.1`, `127.1`), bracketed IPv6 with zone IDs,
  IPv4-mapped/compatible IPv6, NAT64 (`64:ff9b::/96`), 6to4 (`2002::/16`); Teredo (`2001::/32`) is blocked.
  Hosts added via `trustTo()` remain an explicit opt-in and bypass the check.
