<?php

namespace MediaWiki\Extension\UserFlairs\Api;

use ApiQuery;
use ApiQueryBase;
use ApiResult;
use MediaWiki\Extension\UserFlairs\Resolver\FlairResolver;

/**
 * implements action=query&list=userflairgroups
 */
class ApiQueryUserFlairGroups extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly FlairResolver $resolver,
	) {
		parent::__construct( $query, $moduleName, 'ufg' );
	}

	/** @inheritDoc */
	public function execute(): void {
		$groups = $this->resolver->get_catalog();
		ApiResult::setIndexedTagName( $groups, 'group' );
		$this->getResult()->addValue(
			[ 'query' ],
			'userflairgroups',
			$groups
		);
	}

	/** @inheritDoc */
	public function getCacheMode( $params ): string {
		return 'public';
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=userflairgroups'
				=> 'apihelp-query+userflairgroups-example-1',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls(): string {
		return 'https://github.com/obbywiki/mediawiki-extensions-UserFlairs';
	}

}
