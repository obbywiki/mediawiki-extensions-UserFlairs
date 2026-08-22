<?php

namespace MediaWiki\Extension\UserFlairs\Api;

use ApiQuery;
use ApiQueryBase;
use ApiResult;
use MediaWiki\Extension\UserFlairs\Resolver\FlairResolver;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * implements action=query&list=userflairs
 */
class ApiQueryUserFlairs extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly FlairResolver $resolver,
		private readonly UserFactory $user_factory,
	) {
		parent::__construct( $query, $moduleName, 'uf' );
	}

	/** @inheritDoc */
	public function execute(): void {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$items = [];

		foreach ( $params['users'] as $specified ) {
			if ( $specified instanceof UserIdentity ) {
				$user = $specified;
			} else {
				$user = $this->user_factory->newFromName( (string)$specified );
			}
			if ( $user === null || !$user->isRegistered() || $user->getId() <= 0 ) {
				continue;
			}
			$flair = $this->resolver->resolve_for_user( $user );
			$items[] = [
				'user' => $user->getName(),
				'user_id' => $user->getId(),
				'flair' => $flair,
			];
		}

		ApiResult::setIndexedTagName( $items, 'user' );
		$result->addValue( [ 'query' ], 'userflairs', $items );
	}

	/** @inheritDoc */
	public function getCacheMode( $params ): string {
		return 'public';
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'users' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=userflairs&ufusers=Example'
				=> 'apihelp-query+userflairs-example-1',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls(): string {
		return 'https://github.com/obbywiki/mediawiki-extensions-UserFlairs';
	}

}
