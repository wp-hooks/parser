<?php

namespace WP_Parser;

use WP_Parser\Factory\Hook_ as HookStrategy;
use WP_Parser\HooksMetadata;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\File\LocalFile;
use phpDocumentor\Reflection\Php\ProjectFactory;

/**
 * Fixes newline handling in parsed text.
 *
 * DocBlock lines, particularly for descriptions, generally adhere to a given character width. For sentences and
 * paragraphs that exceed that width, what is intended as a manual soft wrap (via line break) is used to ensure
 * on-screen/in-file legibility of that text. These line breaks are retained by phpDocumentor. However, consumers
 * of this parsed data may believe the line breaks to be intentional and may display the text as such.
 *
 * This function fixes text by merging consecutive lines of text into a single line. A special exception is made
 * for text appearing in `<code>` and `<pre>` tags, as newlines appearing in those tags are always intentional.
 *
 * @param string $text
 *
 * @return string
 */
function fix_newlines( $text ) {
	// Non-naturally occurring string to use as temporary replacement.
	$replacement_string = '{{{{{}}}}}';

	// Replace newline characters within 'code' and 'pre' tags with replacement string.
	$text = preg_replace_callback(
		'/(?<=<pre><code>)(.+)(?=<\/code><\/pre>)/s',
		function ( $matches ) use ( $replacement_string ) {
			return preg_replace( '/[\n\r]/', $replacement_string, $matches[1] );
		},
		$text
	);

	// Merge consecutive non-blank lines together by replacing the newlines with a space.
	$text = preg_replace(
		"/[\n\r](?!\s*[\n\r])/m",
		' ',
		$text
	);

	// Restore newline characters into code blocks.
	$text = str_replace( $replacement_string, "\n", $text );

	return $text;
}

/**
 * Extracts the namespace from a Fqsen
 *
 * @param \phpDocumentor\Reflection\Fqsen fqsen
 *
 * @return string
 */
function get_namespace( $fqsen ) {
	$parts = explode( '\\', ltrim( (string) $fqsen, '\\' ) );
	array_pop( $parts );

	return implode( '\\', $parts );
}

/**
 * @param $element
 *
 * @return array
 */
function export_docblock( $element ) {
	$docblock = $element->getDocBlock();
	if ( ! $docblock ) {
		return array(
			'description'      => '',
			'long_description' => '',
			'tags'             => array(),
		);
	}

	$output = array(
		'description'      => preg_replace( '/[\n\r]+/', ' ', $docblock->getSummary() ),
		'long_description' => fix_newlines( $docblock->getDescription() ),
		'tags'             => array(),
	);

	foreach ( $docblock->getTags() as $tag ) {
		$tag_data = array(
			'name' => $tag->getName(),
		);

		if ( method_exists( $tag, 'getDescription' ) ) {
			$tag_data['content'] = preg_replace( '/[\n\r]+/', ' ', $tag->getDescription() );
		}

		if ( method_exists( $tag, 'getType' ) ) {
			$tag_type = $tag->getType();

			if ( ! $tag_type instanceof \phpDocumentor\Reflection\Types\AggregatedType ) {
				$tag_data['types'] = array( (string) $tag_type );
			} else {
				foreach ( $tag_type->getIterator() as $index => $type ) {
					$tag_data['types'][] = (string) $type;
				}
			}
		}

		if ( method_exists( $tag, 'getLink' ) ) {
			$tag_data['link'] = $tag->getLink();
		}
		if ( method_exists( $tag, 'getVariableName' ) ) {
			$variable             = $tag->getVariableName();
			$tag_data['variable'] = $variable ? '$' . $variable : '';
		}
		if ( method_exists( $tag, 'getReference' ) ) {
			$tag_data['refers'] = $tag->getReference();
		}
		if ( method_exists( $tag, 'getVersion' ) ) {
			// Version string.
			$version = $tag->getVersion();
			if ( ! empty( $version ) ) {
				$tag_data['content'] = $version;
			}
			// Description string.
			if ( method_exists( $tag, 'getDescription' ) ) {
				$description = preg_replace( '/[\n\r]+/', ' ', $tag->getDescription() );
				if ( ! empty( $description ) ) {
					$tag_data['description'] = $description;
				}
			}
		}

		$output['tags'][] = $tag_data;
	}

	return $output;
}

/**
 * @param \phpDocumentor\Reflection\Php\Argument[] $arguments
 *
 * @return array
 */
function export_arguments( array $arguments ) {
	$output = array();

	foreach ( $arguments as $argument ) {
		$output[] = array(
			'name'    => '$' . $argument->getName(),
			'default' => $argument->getDefault(),
			'type'    => (string) $argument->getType(),
		);
	}

	return $output;
}

/**
 * @param \phpDocumentor\Reflection\Php\Property[] $properties
 *
 * @return array
 */
function export_properties( array $properties ) {
	$out = array();

	foreach ( $properties as $property ) {
		$out[] = array(
			'name'       => '$' . $property->getName(),
			'line'       => $property->getLocation()->getLineNumber(),
			'end_line'   => $property->getEndLocation()->getLineNumber(),
			'default'    => $property->getDefault(),
			'static'     => $property->isStatic(),
			'visibility' => (string) $property->getVisibility(),
			'doc'        => export_docblock( $property ),
		);
	}

	return $out;
}

/**
 * @param \phpDocumentor\Reflection\Php\Method[] $methods
 *
 * @return array
 */
function export_methods( array $methods ) {
	$output = array();

	foreach ( $methods as $method ) {

		$namespace = get_namespace( $method->getFqsen() );

		$method_data = array(
			'name'       => $method->getName(),
			'namespace'  => $namespace ? $namespace : '',
			'line'       => $method->getLocation()->getLineNumber(),
			'end_line'   => $method->getEndLocation()->getLineNumber(),
			'final'      => $method->isFinal(),
			'abstract'   => $method->isAbstract(),
			'static'     => $method->isStatic(),
			'visibility' => (string) $method->getVisibility(),
			'arguments'  => export_arguments( $method->getArguments() ),
			'doc'        => export_docblock( $method ),
		);

		$output[] = $method_data;
	}

	return $output;
}

/**
 * @param HooksMetadata $hooks_metadata
 *
 * @return array
 */
function export_hooks( HooksMetadata $hooks_metadata ) {
	$out = array();

	foreach ( $hooks_metadata as $hook ) {
		/** @var Hook $hook */
		$out[] = array(
			'name'      => $hook->getName(),
			'line'      => $hook->getLocation()->getLineNumber(),
			'end_line'  => $hook->getEndLocation()->getLineNumber(),
			'type'      => $hook->getType(),
			'arguments' => $hook->getArgs(),
			'doc'       => export_docblock( $hook ),
		);
	}

	return $out;
}

/**
 * @param string $directory
 *
 * @return array
 */
function get_wp_files( $directory ) {

	if ( ! is_dir( $directory ) ) {
		throw new \InvalidArgumentException(
			sprintf( 'Directory [%s] does not exist.', $directory )
		);
	}

	$iterable_files = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $directory )
	);

	$files = array();

	try {
		foreach ( $iterable_files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
	} catch ( \UnexpectedValueException $exc ) {
		return new \RuntimeException(
			sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
		);
	}

	return $files;
}

/**
 * @param array  $files
 * @param string $root
 *
 * @return array
 */
function parse_files( $files, $root ): array {
	$project_files = array();

	foreach ( $files as $file ) {
		$project_files[] = new LocalFile( $file );
	}

	$project_factory = ProjectFactory::createInstance();

	$hook_strategy = new HookStrategy( DocBlockFactory::createInstance() );

	$project_factory->addStrategy( $hook_strategy );

	$project = $project_factory->create( 'WP_Parser', $project_files );

	$output = array();

	/** @var \phpDocumentor\Reflection\Php\File $file */
	foreach ( $project->getFiles() as $file ) {

		$out = array(
			'file' => export_docblock( $file ),
			'path' => ltrim( substr( $file->getPath(), strlen( $root ) ), DIRECTORY_SEPARATOR ),
			'root' => $root,
		);

		foreach ( $file->getIncludes() as $include ) {
			$out['includes'][] = array(
				'name' => $include->getName(),
				'line' => $include->getLocation()->getLineNumber(),
				'type' => $include->getType(),
			);
		}

		/** @var \phpDocument\Reflection\Php\Constant $constant */
		foreach ( $file->getConstants() as $constant ) {
			$out['constants'][] = array(
				'name'  => $constant->getName(),
				'line'  => $constant->getLocation()->getLineNumber(),
				'value' => $constant->getValue(),
			);
		}

		if ( array_key_exists( 'hooks', $file->getMetadata() ) ) {
			$out['hooks'] = export_hooks( $file->getMetadata()['hooks'] );
		}

		/** @var \phpDocument\Reflection\Php\Function_ $function */
		foreach ( $file->getFunctions() as $function ) {

			$namespace = get_namespace( $function->getFqsen() );

			$func = array(
				'name'      => $function->getName(),
				'namespace' => $namespace ? $namespace : 'global',
				'line'      => $function->getLocation()->getLineNumber(),
				'end_line'  => $function->getEndLocation()->getLineNumber(),
				'arguments' => export_arguments( $function->getArguments() ),
				'doc'       => export_docblock( $function ),
				'hooks'     => array(),
			);

			$out['functions'][] = $func;
		}

		/** @var \phpDocument\Reflection\Php\Class_ $class */
		foreach ( $file->getClasses() as $class ) {

			$parts = explode( '\\', ltrim( $class->getFqsen(), '\\' ) );
			array_pop( $parts );

			$namespace = implode( '\\', $parts );

			$class_data = array(
				'name'       => $class->getName(),
				'namespace'  => $namespace ? $namespace : 'global',
				'line'       => $class->getLocation()->getLineNumber(),
				'end_line'   => $class->getEndLocation()->getLineNumber(),
				'final'      => $class->isFinal(),
				'abstract'   => $class->isAbstract(),
				'extends'    => $class->getParent() !== null ? (string) $class->getParent() : '',
				'implements' => $class->getInterfaces(),
				'properties' => export_properties( $class->getProperties() ),
				'methods'    => export_methods( $class->getMethods() ),
				'doc'        => export_docblock( $class ),

			);

			$out['classes'][] = $class_data;
		}

		$output[] = $out;
	}
	return $output;
}
