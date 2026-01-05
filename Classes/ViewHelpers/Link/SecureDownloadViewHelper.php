<?php

declare(strict_types=1);
namespace Digilue\SecureDownloadsViewhelper\ViewHelpers\Link;

use Digilue\SecureDownloadsViewhelper\Service\SecureDownloadLinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * ViewHelper für sichere Download-Links
 *
 * Erzeugt einen sicheren Download-Link für geschützte Dateien mit User-Authentifizierung
 * über die Leuchtfeuer SecureDownloads Extension.
 *
 * WICHTIG - Sicherheitshinweis:
 * Der Dateiname in der generierten URL ist rein kosmetisch (SEO/UX).
 * Die tatsächlich ausgelieferte Datei wird aus dem JWT-Token-Payload bestimmt.
 * Dies verhindert URL-basierte Path-Traversal-Angriffe, ermöglicht aber
 * potentiell Social-Engineering (URL-Dateiname ≠ tatsächliche Datei).
 * Die Middleware ignoriert den URL-Dateinamen komplett und verwendet
 * ausschließlich den signierten Dateipfad aus dem JWT.
 *
 * Unterstützte File-Typen für 'file' Parameter:
 * - TYPO3\CMS\Extbase\Domain\Model\FileReference (Objekt → getOriginalResource())
 * - TYPO3\CMS\Core\Resource\FileReference (Objekt → getOriginalFile())
 * - TYPO3\CMS\Core\Resource\File (Objekt → direkt)
 * - Combined Identifier String (z.B. "2:path/to/file.pdf")
 * - File Path String (z.B. "fileadmin/documents/file.pdf")
 *
 * Beispiel mit File Object (default User-Restriction):
 * <sdl:link.secureDownload file="{energieausweis.berechnung.document}">
 *     Download PDF
 * </sdl:link.secureDownload>
 *
 * Beispiel mit User-Restriction:
 * <sdl:link.secureDownload file="{file}" feuser="{frontendUser.uid}">
 *     Download PDF (nur für User)
 * </sdl:link.secureDownload>
 *
 * Beispiel mit Public Access:
 * TODO implement in SecureDownloads Extension
 * <sdl:link.secureDownload file="{file}" feuser="public">
 *     Download PDF (öffentlich)
 * </sdl:link.secureDownload>
 *
 * Beispiel mit Combined Identifier String:
 * <sdl:link.secureDownload file="2:energieausweise/dokument.pdf">
 *     Download PDF
 * </sdl:link.secureDownload>
 *
 * Beispiel mit HTML-Attributen:
 * <sdl:link.secureDownload file="{file}" class="btn btn-primary" target="_blank" rel="noopener">
 *     PDF öffnen
 * </sdl:link.secureDownload>
 *
 * Generiert: /securedl/sdl-{JWT}/datei.pdf
 * Wobei: {JWT} = signierter Token mit file (Combined Identifier), user, groups, exp
 *        datei.pdf = kosmetisch, wird nicht zur Validierung verwendet
 *
 * Output:
 * <a href="/securedl/sdl-{JWT}/datei.pdf" class="btn btn-primary" target="_blank" rel="noopener">
 *     PDF öffnen
 * </a>
 */
class SecureDownloadViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument('file', 'mixed', 'File reference object (Extbase FileReference, Core FileReference, File) or Combined Identifier string or file path', false);
        $this->registerArgument('feuser', 'mixed', 'Frontend User ID (int) or "public" (string) for public access. If not set, no user restriction.', false);
        $this->registerArgument('timeout', 'string', 'Timeout in seconds. If not set, uses extension default TTL.', false, null);
        $this->registerArgument('siteIdentifier', 'string', 'TYPO3 Site Identifier for domain prefix (needed in Backend context)', false);
    }

    public function render(): string
    {
        $file = $this->arguments['file'] ?? null;
        $feuser = $this->arguments['feuser'] ?? null;

        $service = GeneralUtility::makeInstance(SecureDownloadLinkService::class);

        // Determine resource URI: check if string or object
        $resourceUri = is_string($file) ? $file : $service->extractIdentifierFromFile($file);

        if (!$resourceUri) {
            return '';
        }

        // Determine user ID: int, 'public', or null
        $userId = null;
        if ($feuser !== null) {
            $userId = $feuser === 'public' ? 0 : (int)$feuser;
        }

        // Create secure link via service
        $securedUrl = $service->createSecureLink(
            $resourceUri,
            $userId,
            (int)$this->arguments['timeout'] ?: null,
            $this->arguments['siteIdentifier'] ?? null
        );

        // Set href attribute
        $this->tag->addAttribute('href', $securedUrl);

        // Set content (either children or empty)
        $this->tag->setContent($this->renderChildren() ?? '');

        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }
}