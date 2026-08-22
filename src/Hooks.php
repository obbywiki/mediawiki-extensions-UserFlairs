<?php

namespace MediaWiki\Extension\UserFlairs;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\UserFlairs\Html\FlairHtml;
use MediaWiki\Extension\UserFlairs\Html\FlairPlacement;
use MediaWiki\Extension\UserFlairs\Resolver\FlairResolver;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

class Hooks implements BeforePageDisplayHook {

	public function __construct(
		private readonly Config $config,
		private readonly FlairResolver $resolver,
		private readonly FlairHtml $flair_html,
		private readonly FlairPlacement $placement,
		private readonly UserFactory $user_factory,
	) {
	}
	
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$dir = dirname( __DIR__ ) . '/sql/mysql';
		$updater->addExtensionTable( 'uf_group_flair', "$dir/uf_group_flair.sql" );
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->is_enabled() ) {
			return;
		}
		$this->prepare_header_flair( $out, $skin );
		$this->maybe_upv2_overlay( $out );
	}

	/**
	 * see https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles
	 *
	 * @param array<string,mixed> $profile
	 */
	public function onIntegratedProfilesAfterAvatar( array $profile, string &$html ): void {
		if ( !$this->is_enabled() ) {
			return;
		}
		if ( !empty( $profile['is_private'] ) ) {
			return;
		}
		$user = $this->user_from_profile( $profile );
		if ( $user === null ) {
			return;
		}
		$this->append_flair( $user, $html, FlairHtml::MODE_IN_AVATAR, false );
	}

	/**
	 * best efforts https://www.mediawiki.org/wiki/Extension:UserProfileV2/Hooks/UserProfileV2ProfileAfterMasthead
	 */
	public function onUserProfileV2ProfileAfterMasthead( User $user, &$html ): void {
		if ( !$this->is_enabled() ) {
			return;
		}

		$html = (string)$html;
		$this->append_flair( $user, $html, FlairHtml::MODE_RELOCATABLE, true );
	}

	private function is_enabled(): bool {
		return (bool)$this->config->get( 'UserFlairsEnabled' );
	}

	/**
	 * overlay for the UserMenu button on Citizen. should work with/without IP or any PFP integration i hope
	 */
	private function prepare_header_flair( OutputPage $out, $skin ): void {
		if ( $skin->getSkinName() !== 'citizen' ) { return; }

		$user = $out->getUser();
		$flair = $this->resolver->resolve_for_user( $user );

		if ( $flair === null ) { return; }

		$out->addJsConfigVars( 'wgUserFlairsHeader', [
			'url' => $flair['url'],
			'label' => $flair['label'] ?? '',
			'group' => $flair['group'] ?? '',
		] );
		$out->addModules( [ 'ext.UserFlairs.header' ] );
	}

	private function maybe_upv2_overlay( OutputPage $out ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'IntegratedProfiles' ) ) { return; }
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UserProfileV2' ) ) { return; }

		$title = $out->getTitle();
		$user = $this->user_from_user_page( $title );
		if ( $user === null || $this->placement->is_placed( $user->getId() ) ) { return; }

		$flair = $this->resolver->resolve_for_user( $user );
		if ( $flair === null ) { return; }

		$out->addJsConfigVars( 'wgUserFlairs', $flair );
		$this->add_overlay_modules( $out, true );
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	private function user_from_profile( array $profile ): ?UserIdentity {
		$user_id = (int)( $profile['user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$user = $this->user_factory->newFromId( $user_id );
			if ( $user->isRegistered() ) {
				return $user;
			}
		}


		$name = trim( (string)( $profile['user_name'] ?? '' ) );
		if ( $name === '' ) { return null; }

		$user = $this->user_factory->newFromName( $name );
		if ( $user === null || !$user->isRegistered() ) { return null; }

		return $user;
	}

	private function user_from_user_page( ?Title $title ): ?UserIdentity {
		if ( $title === null || !$title->inNamespace( NS_USER ) || $title->isSubpage() ) { return null; }

		$user = $this->user_factory->newFromName( $title->getRootText() );
		if ( $user === null || !$user->isRegistered() ) { return null; }

		return $user;
	}

	private function append_flair( UserIdentity $user, string &$html, string $mode, bool $needs_js ): void {
		$user_id = $user->getId();
		if ( $this->placement->is_placed( $user_id ) ) { return; }

		$flair = $this->resolver->resolve_for_user( $user );
		if ( $flair === null ) { return; }

		$html .= $this->flair_html->render( $flair, $mode );
		$this->placement->mark( $user_id );
		$this->add_overlay_modules( RequestContext::getMain()->getOutput(), $needs_js );
	}

	private function add_overlay_modules( OutputPage $out, bool $needs_js ): void {
		$out->addModuleStyles( [ 'ext.UserFlairs.styles' ] );
		if ( $needs_js ) {
			$out->addModules( [ 'ext.UserFlairs' ] );
		}
	}

}
