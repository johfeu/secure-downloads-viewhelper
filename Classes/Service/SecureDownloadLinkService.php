<?php

declare(strict_types=1);
namespace Digilue\SecureDownloadsViewhelper\Service;

use Leuchtfeuer\SecureDownloads\Factory\SecureLinkFactory;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;

/**
 * Service für die Generierung von sicheren Download-Links
 *
 * Dieser Service kapselt die Logik zur Erstellung von JWT-signierten Download-URLs
 * über die Leuchtfeuer SecureDownloads Extension.
 */
class SecureDownloadLinkService
{
    public function __construct(
        private readonly SecureLinkFactory $secureLinkFactory,
        private readonly SiteFinder $siteFinder
    ) {
    }

    /**
     * Erstellt einen sicheren Download-Link
     *
     * @param string $resourceUri Combined Identifier oder File Path
     * @param int|null $userId User ID für Access Restriction, oder null
     * @param int|null $timeout Link Timeout in Sekunden, oder null für Default TTL
     * @param string|null $siteIdentifier TYPO3 Site Identifier für Domain-Prefix (für Backend-Kontext)
     * @return string Die gesicherte Download-URL mit JWT Token
     */
    public function createSecureLink(
        string $resourceUri,
        ?int $userId = null,
        ?int $timeout = null,
        ?string $siteIdentifier = null
    ): string {
        $factory = clone $this->secureLinkFactory;
        $factory = $factory->withResourceUri($resourceUri);

        // Only set user if provided
        if ($userId !== null) {
            $factory = $factory->withUser($userId);
        }

        // Only set timeout if provided
        if ($timeout !== null) {
            $factory = $factory->withLinkTimeout($timeout);
        }

        $url = $factory->getUrl();

        // Prepend domain if siteIdentifier is provided (for Backend context)
        if ($siteIdentifier !== null) {
            $url = $this->prependSiteDomain($url, $siteIdentifier);
        }

        return $url;
    }

    /**
     * Extrahiert Combined Identifier aus verschiedenen File-Objekt-Typen
     *
     * Unterstützt Extbase FileReference, Core FileReference und File Objekte.
     * Wird von ViewHelpern verwendet, um aus File-Objekten den Resource-Identifier zu extrahieren.
     *
     * @param mixed $file Extbase FileReference, Core FileReference, oder File Objekt
     * @return string|null Combined Identifier oder null wenn ungültig
     */
    public function extractIdentifierFromFile(mixed $file): ?string
    {
        return match (true) {
            $file instanceof ExtbaseFileReference => $file->getOriginalResource()->getOriginalFile()->getCombinedIdentifier(),
            $file instanceof CoreFileReference => $file->getOriginalFile()->getCombinedIdentifier(),
            $file instanceof File => $file->getCombinedIdentifier(),
            default => null,
        };
    }

    /**
     * Fügt die Site-Domain zur URL hinzu
     *
     * Wird benötigt, wenn SecureDownloadLinks im Backend-Kontext generiert werden,
     * da dort kein Frontend-Request mit Site-Information verfügbar ist.
     *
     * @param string $url Relative URL
     * @param string $siteIdentifier TYPO3 Site Identifier
     * @return string Absolute URL mit Domain
     */
    protected function prependSiteDomain(string $url, string $siteIdentifier): string
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $base = $site->getBase();

            $domain = $base->getScheme() . '://'
                . $base->getHost()
                . ($base->getPort() ? ':' . (string)$base->getPort() : '');

            // Ensure single slash between domain and path
            return rtrim($domain, '/') . '/' . ltrim($url, '/');
        } catch (\Exception) {
            // Fallback: return original URL if site not found
            return $url;
        }
    }
}