Changelog
=========

2.1.0 (2025-05-23)
------------------

- Added replacement of `Form::hiddenForm` macro


2.0.0 (2025-05-21)
------------------

- Added support for Laravel 12
- Reverse behavior of "Escaped echo without double-encode":
    - removed `--escape-with-double-encode` command option
    - added `--escape-without-double-encode` command option


1.1.2 (2024-06-27)
------------------

- Fix: unexpected behavior with comments
- Fix: 0 was considered as empty value


1.1.1 (2024-06-24)
------------------

- Fixed support of `--escape-with-double-encode` command option
- Added description for `--escape-with-double-encode` command option


1.1.0 (2024-06-12)
------------------

- Added `--escape-with-double-encode` command option


1.0.1 (2024-04-26)
------------------

- Fix tabulations matching
- Fix route rebuilding from `Form::open` when more than one parameter
- Fix bad replacement when escaped quotes are present
- Do not replace if commented with `{{-- --}}` (can conflict)


1.0.0 (2024-04-24)
------------------

- First release.
