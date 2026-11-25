<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Model;

/**
 * Legal document types offered by eRecht24. The values are the API names,
 * used as path segment for pulls and as `erecht24_type` in pushes.
 */
enum DocumentType: string
{
    case Imprint = 'imprint';
    case PrivacyPolicy = 'privacyPolicy';
    case PrivacyPolicySocialMedia = 'privacyPolicySocialMedia';
}
