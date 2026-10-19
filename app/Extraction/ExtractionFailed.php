<?php

namespace App\Extraction;

use RuntimeException;

/**
 * A problem with the document itself. Retrying won't help, so the job fails
 * straight away and shows this message to the reviewer.
 */
class ExtractionFailed extends RuntimeException {}
