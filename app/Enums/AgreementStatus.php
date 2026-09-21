<?php

namespace App\Enums;

enum AgreementStatus: string
{
    /** Being written. Editable, deletable, binding on nobody. */
    case Draft = 'draft';

    /** The one version in force. Immutable from the moment it is published. */
    case Published = 'published';

    /** Superseded by a newer version. Still immutable; acceptances of it stand. */
    case Retired = 'retired';
}
