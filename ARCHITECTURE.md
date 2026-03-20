# Architecture: pclzip

## Purpose

PclZip is a pure-PHP ZIP archive library (v2.8.2) by Vincent Blavet. It creates, reads, extracts, and inspects ZIP archives without requiring the `zip` PHP extension, relying only on `zlib` for deflate/inflate support.

## Directory Structure

```
pclzip/
  pclzip.lib.php   — entire library: constants, PclZip class, internal helpers
  readme.txt       — user-facing documentation and API reference
```

## Key Design Decisions

- **Single-file distribution**: The entire library lives in one file (`pclzip.lib.php`) for easy drop-in inclusion — no Composer autoloading required.
- **No zip extension dependency**: All ZIP parsing and generation is implemented in pure PHP using only the `zlib` extension for compression. This maximises hosting compatibility.
- **Callback-based pre/post processing**: Archive and extract operations accept optional callback functions (`PCLZIP_CB_PRE_ADD`, `PCLZIP_CB_POST_ADD`, `PCLZIP_CB_PRE_EXTRACT`, `PCLZIP_CB_POST_EXTRACT`) so callers can filter, rename, or reject entries without subclassing.
- **Configurable constants**: Runtime behaviour (block size, filename separator, temporary directory, memory/file ratio threshold) is controlled via `define()` guards so integrators can tune behaviour before including the file.
- **Temporary-file strategy**: When a file being added exceeds `memory_limit × PCLZIP_TEMPORARY_FILE_RATIO`, PclZip falls back to temporary-file buffering to avoid exhausting PHP memory.
- **Error model**: Errors are reported via integer error codes stored on the `PclZip` instance and returned from methods (0 = failure, non-zero = success or entry count). An optional external `PclError` library can be enabled via `PCLZIP_ERROR_EXTERNAL`.

## Public API (PclZip class)

| Method | Description |
|---|---|
| `create($filelist, ...)` | Create a new archive from files/directories |
| `add($filelist, ...)` | Add files to an existing archive |
| `listContent()` | Return array of entry descriptors |
| `extract($what, ...)` | Extract all or selected entries |
| `extractByIndex($index, ...)` | Extract entries by numeric index |
| `delete($what)` | Remove entries from the archive |
| `merge($archive)` | Merge another archive into this one |
| `duplicate()` | Clone the archive to a new file |
| `errorCode()` / `errorName()` / `errorInfo()` | Retrieve last error details |

## Extension Points

- **Pre/post callbacks**: Pass `PCLZIP_OPT_CALL_PRE_ADD` / `PCLZIP_OPT_CALL_POST_EXTRACT` etc. as variadic options to any operation.
- **Path rewriting**: `PCLZIP_OPT_REMOVE_PATH`, `PCLZIP_OPT_ADD_PATH`, and `PCLZIP_OPT_REMOVE_ALL_PATH` reshape stored paths at add/extract time.
- **Password protection**: `PCLZIP_OPT_CRYPT` enables traditional ZIP encryption (note: not AES).

## Dependency Flow

```
Caller
  → PclZip::create() / add() / extract()
      → internal privAdd / privExtract helpers
          → zlib (gzdeflate / gzinflate) for compression
          → filesystem (fopen / fread / fwrite) for I/O
          → temporary files when entry size > memory threshold
```
