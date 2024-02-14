<?php

namespace WP_Parser;

use ArrayObject;
use phpDocumentor\Reflection\Metadata\Metadata;

class HooksMetadata extends ArrayObject implements Metadata {

	public function key(): string {
		return 'hooks';
	}
}
