<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Document;

/**
 * Thrown by a document handler that does not accept the text because the site
 * owner maintains it locally. The push is answered with HTTP 422.
 */
final class DocumentManagedLocallyException extends \RuntimeException
{
}
