<?php

namespace MediaWiki\Extension\UserFlairs\Specials;

use HtmlArmor;
use MediaWiki\Extension\UserFlairs\Cache\FlairCache;
use MediaWiki\Extension\UserFlairs\File\FlairFileLookup;
use MediaWiki\Extension\UserFlairs\Store\FlairStore;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\UserGroupManager;

class SpecialUserFlairs extends SpecialPage {

	public function __construct(
		private readonly FlairStore $store,
		private readonly FlairCache $cache,
		private readonly FlairFileLookup $file_lookup,
		private readonly UserGroupManager $user_group_manager,
	) {
		// 1.46 deprecated constructor $restriction; 1.43 still reads it
		if ( version_compare( MW_VERSION, '1.46', '<' ) ) {
			parent::__construct( 'UserFlairs', 'userflairs-manage' );
			return;
		}

		parent::__construct( 'UserFlairs' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'userflairs-manage';
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->checkPermissions();
		$this->checkReadOnly();

		$group = $this->normalize_subpage( (string)$subPage );
		if ( $group !== '' ) {
			$this->execute_group( $group );
			return;
		}

		$this->execute_index();
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'users';
	}

	private function execute_index(): void {
		$this->outputHeader();
		$out = $this->getOutput();
		$out->addBodyClasses( [ 'uf-manage-page' ] );
		$out->addModuleStyles( [ 'ext.UserFlairs.styles', 'ext.UserFlairs.manage.styles' ] );
		$out->addModules( [ 'ext.UserFlairs.manage' ] );
		$this->maybe_show_notice();

		$assigned = $this->assigned_rows();
		$assigned_ids = [];
		foreach ( $assigned as $row ) {
			$assigned_ids[$row['group']] = true;
		}

		$unassigned = [];
		foreach ( $this->list_groups() as $group ) {
			if ( !isset( $assigned_ids[$group] ) ) {
				$unassigned[] = $group;
			}
		}

		if ( $assigned === [] ) {
			$out->addHTML( Html::rawElement( 'p', [ 'class' => 'uf-manage__help' ], $this->msg( 'userflairs-no-assigned' )->escaped() ) );
		} else {
			$out->addHTML( Html::element( 'h2', [ 'class' => 'uf-manage__section' ], $this->msg( 'userflairs-assigned' )->text() ) );
			$out->addHTML( Html::rawElement( 'p', [ 'class' => 'uf-manage__intro' ], $this->msg( 'userflairs-reorder-help' )->escaped() ) );

			$form = HTMLForm::factory( 'ooui', [
				'order' => [
					'type' => 'hidden',
					'default' => implode( '|', array_column( $assigned, 'group' ) ),
					'id' => 'uf-order-field',
				],
			], $this->getContext() );
			$form->setId( 'userflairs-order' );
			$form->setFormIdentifier( 'userflairs-order' );
			$form->setPreHtml( $this->render_assigned_list( $assigned ) );
			$form->setFooterHtml( $this->unsaved_notice() );
			$form->setSubmitTextMsg( 'userflairs-save' );
			$form->setSubmitCallback( [ $this, 'on_submit_order' ] );
			if ( $form->show() ) {
				return;
			}
		}

		if ( $unassigned === [] ) {
			return;
		}

		$out->addHTML( Html::element( 'h2',
			[ 'class' => 'uf-manage__section' ],
			$this->msg( 'userflairs-unassigned' )->text()
		) );
		$items = '';
		foreach ( $unassigned as $group ) {
			$items .= Html::rawElement( 'li', [ 'class' => 'uf-manage__item uf-manage__item--plain' ], $this->group_link( $group, $this->thumb_html( null ) ) );
		}
		$out->addHTML( Html::rawElement( 'ul', [ 'class' => 'uf-manage__list uf-manage__list--plain' ], $items ) );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function on_submit_order( array $data ): Status {
		$raw = trim( (string)( $data['order'] ?? '' ) );
		$groups = $raw === '' ? [] : explode( '|', $raw );
		$allowed = array_fill_keys( $this->list_groups(), true );
		$clean = [];

		foreach ( $groups as $group ) {
			$group = trim( $group );
			if ( $group !== '' && isset( $allowed[$group] ) && !isset( $clean[$group] ) ) {
				$clean[$group] = true;
			}
		}

		$this->store->save_order( array_keys( $clean ) );
		$this->cache->purge();

		return $this->redirect_with_notice( 'saved' );
	}

	private function execute_group( string $group ): void {
		if ( !in_array( $group, $this->list_groups(), true ) ) {
			$this->getOutput()->addWikiMsg( 'userflairs-unknown-group' );
			return;
		}

		$label = $this->group_label( $group );
		$out = $this->getOutput();
		$out->addBodyClasses( [ 'uf-manage-page' ] );
		$out->setPageTitleMsg( $this->msg( 'userflairs-group-title', $label ) );
		$out->addModuleStyles( [
			'ext.UserFlairs.styles',
			'ext.UserFlairs.manage.styles',
		] );
		$this->maybe_show_notice();
		$out->addHTML( Html::rawElement(
			'p',
			[ 'class' => 'uf-manage__back' ],
			$this->getLinkRenderer()->makeKnownLink(
				$this->getPageTitle(),
				$this->msg( 'userflairs-back' )->text()
			)
		) );

		$upload = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'Upload' ),
			$this->msg( 'userflairs-upload-link' )->text()
		);
		$out->addHTML( Html::rawElement(
			'p',
			[ 'class' => 'uf-manage__intro' ],
			$this->msg( 'userflairs-intro' )->rawParams( $upload )->parse()
		) );

		$current = $this->row_for_group( $group );
		$file_default = $current['file'] ?? '';
		$preview = '';

		if ( $file_default !== '' ) {
			$described = $this->file_lookup->describe( $file_default );
			if ( $described !== null ) {
				$preview = Html::rawElement(
					'div',
					[ 'class' => 'uf-manage__preview-frame' ],
					Html::element( 'img', [
						'class' => 'uf-manage__preview uf-manage__preview--lg',
						'src' => $described['url'],
						'width' => $described['width'],
						'height' => $described['height'],
						'alt' => '',
						'draggable' => 'false',
					] )
				);
			}
		}

		$descriptor = [
			'file' => [
				'type' => 'text',
				'label-message' => 'userflairs-file',
				'default' => $file_default,
			],
		];

		if ( $preview !== '' ) {
			$descriptor = [
				'preview' => [
					'type' => 'info',
					'raw' => true,
					'default' => $preview,
				],
			] + $descriptor;
		}

		$form = HTMLForm::factory( 'ooui', $descriptor, $this->getContext() );
		$form->setId( 'userflairs-group' );
		$form->setFormIdentifier( 'userflairs-group-' . $group );
		$form->setSubmitTextMsg( 'userflairs-save' );
		$form->addButton( [
			'name' => 'uf-remove',
			'value' => '1',
			'label-message' => 'userflairs-remove',
			'flags' => [ 'destructive' ],
		] );
		$form->setSubmitCallback( function ( array $data ) use ( $group ) {
			return $this->on_submit_group( $group, $data );
		} );
		$form->show();
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function on_submit_group( string $group, array $data ): Status {
		if ( $this->getRequest()->getCheck( 'uf-remove' ) ) {
			$this->store->delete_group( $group );
			$this->cache->purge();

			return $this->redirect_with_notice( 'removed' );
		}

		$file_raw = trim( (string)( $data['file'] ?? '' ) );
		if ( $file_raw === '' ) {
			$this->store->delete_group( $group );
			$this->cache->purge();

			return $this->redirect_with_notice( 'removed' );
		}

		$described = $this->file_lookup->describe( $file_raw );
		if ( $described === null ) {
			return Status::newFatal( 'userflairs-invalid-file', $file_raw );
		}

		$this->store->save_group( $group, $described['file'] );
		$this->cache->purge();

		return $this->redirect_with_notice( 'saved', $group );
	}

	/**
	 * @param list<array{group:string,file:string,priority:int}> $assigned
	 */
	private function render_assigned_list( array $assigned ): string {
		$total = count( $assigned );
		$items = '';
		$index = 0;
		foreach ( $assigned as $row ) {
			$index++;
			$described = $this->file_lookup->describe( $row['file'] );
			$items .= Html::rawElement( 'li',
				[
					'class' => 'uf-manage__item uf-manage__item--assigned',
					'data-group' => $row['group'],
				],
				$this->group_link( $row['group'], $this->thumb_html( $described ) )
					. $this->drag_handle( $index, $total )
			);
		}

		$live = Html::element( 'div', [
			'class' => 'uf-manage__live',
			'id' => 'uf-manage-live',
			'aria-live' => 'polite',
			'aria-atomic' => 'true',
		] );

		return $live . Html::rawElement( 'ol',
			[
				'class' => 'uf-manage__list uf-manage__list--assigned',
				'id' => 'uf-order-list',
			],
			$items
		);
	}

	private function maybe_show_notice(): void {
		$notice = $this->getRequest()->getVal( 'notice', '' );
		$messages = [ 'saved' => 'userflairs-saved', 'removed' => 'userflairs-removed' ];
		if ( !isset( $messages[$notice] ) ) {
			return;
		}

		$out = $this->getOutput();
		$out->addModuleStyles( [ 'mediawiki.codex.messagebox.styles' ] );
		$out->addHTML(
			Html::successBox( $this->msg( $messages[$notice] )->escaped(), 'uf-manage__notice' )
		);
	}

	private function redirect_with_notice( string $notice, string $subpage = '' ): Status {
		$title = $subpage === '' ? $this->getPageTitle() : $this->getPageTitle( $subpage );
		$this->getOutput()->redirect( $title->getFullURL( [ 'notice' => $notice ] ) );

		return Status::newGood();
	}

	private function unsaved_notice(): string {
		return Html::rawElement( 'div',
			[
				'class' => 'uf-manage__unsaved',
				'hidden' => true,
				'id' => 'uf-manage-unsaved',
			],
			Html::element( 'span', [], $this->msg( 'userflairs-unsaved' )->text() )
		);
	}

	private function drag_handle( int $position, int $total ): string {
		$label = $this->msg( 'userflairs-drag-handle' )->text();
		$described = $this->msg( 'userflairs-position', $position, $total )->text();
		$icon = '<svg class="uf-manage__handle-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 13a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm0-4a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm-4 4a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm5-9a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM6 5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>';
		// thank you https://github.com/primer/octicons

		return Html::rawElement( 'button', [ 'type' => 'button', 'class' => 'uf-manage__handle', 'title' => $label, 'aria-label' => $label . ', ' . $described ], $icon );
	}

	/**
	 * @param array{file:string,url:string,width:int,height:int}|null $described
	 */
	private function thumb_html( ?array $described ): string {
		$inner = '';
		if ( $described !== null ) {
			$inner = Html::element( 'img', [
				'class' => 'uf-manage__preview',
				'src' => $described['url'],
				'width' => $described['width'],
				'height' => $described['height'],
				'alt' => '',
				'draggable' => 'false',
			] );
		}

		return Html::rawElement( 'span', [
			'class' => $described === null
				? 'uf-manage__thumb uf-manage__thumb--empty'
				: 'uf-manage__thumb',
		], $inner );
	}

	private function group_link( string $group, string $prefix = '' ): string {
		$inner = $prefix . Html::rawElement( 'span', [ 'class' => 'uf-manage__body' ],
			Html::element( 'span', [ 'class' => 'uf-manage__label' ], $this->group_label( $group ) ) . Html::element( 'span', [ 'class' => 'uf-manage__id' ], $group )
		);

		return $this->getLinkRenderer()->makeKnownLink(
			$this->getPageTitle( $group ),
			new HtmlArmor( $inner ),
			[ 'class' => 'uf-manage__link' ]
		);
	}

	/**
	 * @return list<array{group:string,file:string,priority:int}>
	 */
	private function assigned_rows(): array {
		$allowed = array_fill_keys( $this->list_groups(), true );
		$rows = [];
		foreach ( $this->store->get_all() as $row ) {
			if ( isset( $allowed[$row['group']] ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * @return array{group:string,file:string,priority:int}|null
	 */
	private function row_for_group( string $group ): ?array {
		foreach ( $this->store->get_all() as $row ) {
			if ( $row['group'] === $group ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private function list_groups(): array {
		$groups = $this->user_group_manager->listAllGroups();
		foreach ( [ 'user', 'autoconfirmed' ] as $implicit ) {
			if ( !in_array( $implicit, $groups, true ) ) {
				$groups[] = $implicit;
			}
		}
		sort( $groups );

		return array_values( $groups );
	}

	private function group_label( string $group ): string {
		$msg = $this->msg( 'group-' . $group );
		if ( $msg->exists() ) {
			return $msg->text();
		}

		return $this->getLanguage()->getGroupName( $group );
	}

	private function normalize_subpage( string $subPage ): string {
		$subPage = str_replace( '_', ' ', trim( rawurldecode( $subPage ) ) );
		if ( $subPage === '' ) {
			return '';
		}
		
		foreach ( $this->list_groups() as $group ) {
			if ( strcasecmp( $group, $subPage ) === 0 ) {
				return $group;
			}
		}

		return $subPage;
	}

}
