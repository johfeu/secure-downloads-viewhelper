# Secure Downloads ViewHelper

TYPO3 Extension providing ViewHelpers for secure download links using the Leuchtfeuer SecureDownloads Extension.

## Features

- **Link ViewHelper**: Generates secure `<a>` tags with JWT-signed download URLs
- **URI ViewHelper**: Generates only the secure URL (for custom link components)
- **User-specific Access**: Restrict downloads to specific frontend users
- **Backend Support**: Generate absolute URLs with `siteIdentifier` parameter
- **Flexible Timeout**: Configure link expiration time

## Installation

```bash
composer require digilue/secure-downloads-viewhelper
```

## Usage

### Namespace Declaration

```html
xmlns:sdl="http://typo3.org/ns/Digilue/SecureDownloadsViewhelper/ViewHelpers"
```

### Link ViewHelper

Generates a complete `<a>` tag with secure download URL:

```html
<!-- Basic usage with file object -->
<sdl:link.secureDownload file="{document}">
    Download PDF
</sdl:link.secureDownload>

<!-- With user restriction -->
<sdl:link.secureDownload file="{file}" feuser="{frontendUser}">
    Download (user-specific)
</sdl:link.secureDownload>

<!-- With HTML attributes -->
<sdl:link.secureDownload file="{file}" class="btn btn-primary" target="_blank">
    Open PDF
</sdl:link.secureDownload>

<!-- Backend context with absolute URL -->
<sdl:link.secureDownload file="{file}" siteIdentifier="my-landingpage">
    Download
</sdl:link.secureDownload>

<!-- With custom timeout (relative time) -->
<sdl:link.secureDownload file="{file}" timeout="{f:format.date(date: '+2 weeks', format: 'U')}">
    Download (valid for 2 weeks)
</sdl:link.secureDownload>
```

### URI ViewHelper

Returns only the secure URL string (without HTML tag):

```html
<!-- In Backend modules with external link component -->
<f:link.external uri="{sdl:uri.secureDownload(file: document, siteIdentifier: 'powerpass')}">
    Download PDF
</f:link.external>

<!-- As variable -->
<f:variable name="downloadUrl">{sdl:uri.secureDownload(file: file, feuser: user.uid)}</f:variable>

<!-- With combined identifier string -->
{sdl:uri.secureDownload(file: '2:energieausweise/dokument.pdf')}
```

## Parameters

### `file` (mixed, required)
Accepts multiple input types:
- **File Objects**: `TYPO3\CMS\Extbase\Domain\Model\FileReference`
- **Core References**: `TYPO3\CMS\Core\Resource\FileReference`
- **File**: `TYPO3\CMS\Core\Resource\File`
- **Combined Identifier**: String like `"2:path/to/file.pdf"`
- **File Path**: String like `"fileadmin/documents/file.pdf"`

### `feuser` (mixed, optional)
- **Integer**: Frontend User UID for user-specific access
- **"public"**: Public access (requires SecureDownloads Extension support)
- **null** (default): No user restriction

### `timeout` (string, optional)
Link expiration time in seconds (Unix timestamp).

Use `<f:format.date>` for relative times:
```html
timeout="{f:format.date(date: '+1 week', format: 'U')}"    <!-- 1 week -->
timeout="{f:format.date(date: '+30 days', format: 'U')}"   <!-- 30 days -->
timeout="{f:format.date(date: '+3 months', format: 'U')}"  <!-- 3 months -->
```

### `siteIdentifier` (string, optional)
TYPO3 Site Identifier for generating absolute URLs e.g. in Backend context.

Example: `siteIdentifier="langingpage"`

## Security

### Path Traversal Protection
The filename in the URL is **cosmetic only** (for SEO/UX). The actual file delivered is determined from the JWT token payload, preventing URL-based path traversal attacks.

**Example:**
- URL: `/securedl/sdl-{JWT}/nicename.pdf`
- Actual file: Determined by signed Combined Identifier in JWT

### JWT Token Structure
```
{
  "file": "2:documents/document.pdf",  // Combined Identifier
  "user": 123,                               // Frontend User UID
  "groups": [1, 2],                          // User groups
  "exp": 1767699999                          // Expiration timestamp
}
```

## Requirements

- TYPO3 12.4+ or 13.x
- leuchtfeuer/secure-downloads ^6.1

## Known Issues

### SecureLinkFactory::withUser() Bug
**Location**: `vendor/leuchtfeuer/secure-downloads/Classes/Factory/SecureLinkFactory.php:160`

The `withUser()` method modifies the token but exhibits inconsistent behavior - the logged-in user context persists even after setting a different user. This may affect user-specific access control.

**Workaround**: Ensure proper user context is set before generating links, or validate user access server-side.

### JWT Page Field Not Validated
Note: The Page field is stored in the JWT token but not validated during file delivery (no PageCheck class registered in SecureDownloads Extension).

### EncodeCache Side Effects
The internal encode cache in SecureLinkFactory can lead to unexpected results when modifying tokens multiple times. Check if we need to clone the factory instance to avoid cache pollution.

## TODOs

- [ ] **Public Access**: Implement and test `feuser="public"` support in SecureDownloads Extension. Use case: create public links (timout restricted) even with valid fe user session.