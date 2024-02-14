<?php

declare(strict_types=1);

namespace WP_Parser\Factory;

use WP_Parser\Hook;
use WP_Parser\HooksMetadata;

use phpDocumentor\Reflection\Php\Factory\AbstractFactory;
use phpDocumentor\Reflection\Php\Factory\ContextStack;
use phpDocumentor\Reflection\Php\StrategyContainer;
use phpDocumentor\Reflection\Location;
use phpDocumentor\Reflection\Php\File as FileElement;

use PhpParser\Node\Arg;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * Strategy to convert `hook` expressions to HookElement
 */

final class Hook_ extends AbstractFactory {
	public function matches( ContextStack $context, $node ): bool {
		if ( ! $node instanceof Expression ) {
			return false;
		}

		$expression = $node->expr;

		// is 'filter'
		if ( $expression instanceof Assign ) {
			$expression = $expression->expr;
		}

		if ( ! $expression instanceof FuncCall ) {
			return false;
		}

		if ( ! $expression->name instanceof Name ) {
			return false;
		}

		$calling = (string) $expression->name;

		$functions = array(
			'apply_filters',
			'apply_filters_ref_array',
			'apply_filters_deprecated',
			'do_action',
			'do_action_ref_array',
			'do_action_deprecated',
		);

		// is hook?
		return in_array( $calling, $functions );
	}
	/**
	 * @param mixed $node
	 */
	protected function doCreate( ContextStack $context, $node, StrategyContainer $strategies ): void {
		$expression = $node->expr;

		// is 'filter'
		if ( $expression instanceof Assign ) {
			$expression = $expression->expr;
		}

		assert( $expression instanceof FuncCall );

		$file = $context->search( FileElement::class );
		assert( $file instanceof FileElement );

		$expression_args = $expression->args;

		$name         = $this->determineName( $expression );
		$doc_block    = $this->createDocBlock( $node->getDocComment(), $context->getTypeContext() );
		$type         = $this->determineType( $expression );
		$args         = $this->determineArgs( $expression );
		$location     = new Location( $node->getLine() );
		$end_location = new Location( $node->getLine() );

		$hook = new Hook( $name, $doc_block, $type, $args, $location, $end_location );

		if ( ! array_key_exists( 'hooks', $file->getMetadata() ) ) {
			$file->addMetadata( new HooksMetadata() );
		}

		$file->getMetadata()['hooks'][] = $hook;
	}

	private function determineName( FuncCall $expression ): string {
		$filter_name  = $expression->args[0];
		$name         = $this->determineValue( $filter_name );
		$cleanup_name = $this->cleanupName( $name );

		return $cleanup_name;
	}
	/**
	 * @return string[]
	 */
	private function determineArgs( FuncCall $expression ): array {
		$args = $expression->args;

		// Skip the filter name
		array_shift( $args );

		$processed_args = array();

		foreach ( $args as $arg ) {
			$processed_args[] = $this->determineValue( $arg );
		}

		return $processed_args;
	}

	private function determineValue( Arg $value ): string {
		$value_converter = new PrettyPrinter();
		return $value_converter->prettyPrintExpr( $value->value );
	}

	private function determineType( FuncCall $expression ): string {
		$name = (string) $expression->name;

		$type = 'filter';
		switch ( $name ) {
			case 'do_action':
				$type = 'action';
				break;
			case 'do_action_ref_array':
				$type = 'action_reference';
				break;
			case 'do_action_deprecated':
				$type = 'action_deprecated';
				break;
			case 'apply_filters_ref_array':
				$type = 'filter_reference';
				break;
			case 'apply_filters_deprecated';
				$type = 'filter_deprecated';
				break;
		}

		return $type;
	}


	/**
	 * @param string $name
	 *
	 * @return string
	 */
	private function cleanupName( $name ): string {
		$matches = array();

		// quotes on both ends of a string
		if ( preg_match( '/^[\'"]([^\'"]*)[\'"]$/', $name, $matches ) ) {
			return $matches[1];
		}

		// two concatenated things, last one of them a variable
		if ( preg_match(
			'/(?:[\'"]([^\'"]*)[\'"]\s*\.\s*)?' . // First filter name string (optional)
			'(\$[^\s]*)' . // Dynamic variable
			'(?:\s*\.\s*[\'"]([^\'"]*)[\'"])?/',  // Second filter name string (optional)
			$name,
			$matches
		) ) {

			if ( isset( $matches[3] ) ) {
				return $matches[1] . '{' . $matches[2] . '}' . $matches[3];
			} else {
				return $matches[1] . '{' . $matches[2] . '}';
			}
		}

		return $name;
	}
}
