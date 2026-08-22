<?php

namespace MediaWiki\Extension\UserFlairs\Store;

use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * for group/flair assignments (first group in the list gets the highest priority and thefore is chosen first)
 */
class FlairStore {

	public function __construct(
		private readonly IConnectionProvider $connection_provider,
	) {
	}

	/**
	 * @return list<array{group:string,file:string,priority:int}>
	 */
	public function get_all(): array {
		$dbr = $this->connection_provider->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'ufgf_group', 'ufgf_file', 'ufgf_priority' ] )
			->from( 'uf_group_flair' )
			->orderBy( 'ufgf_priority', 'DESC' )
			->orderBy( 'ufgf_group', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rows = [];
		foreach ( $res as $row ) {
			$rows[] = [
				'group' => (string)$row->ufgf_group,
				'file' => (string)$row->ufgf_file,
				'priority' => (int)$row->ufgf_priority,
			];
		}

		return $rows;
	}

	public function save_group( string $group, string $file ): void {
		$group = trim( $group );
		$file = trim( $file );
		if ( $group === '' || $file === '' ) {
			return;
		}

		$dbw = $this->connection_provider->getPrimaryDatabase();
		$now = $dbw->timestamp( ConvertibleTimestamp::now() );
		$existing = $dbw->newSelectQueryBuilder()
			->select( [ 'ufgf_priority' ] )
			->from( 'uf_group_flair' )
			->where( [ 'ufgf_group' => $group ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $existing ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'uf_group_flair' )
				->set( [
					'ufgf_file' => $file,
					'ufgf_updated_at' => $now,
				] )
				->where( [ 'ufgf_group' => $group ] )
				->caller( __METHOD__ )
				->execute();
			return;
		}

		$min = $dbw->newSelectQueryBuilder()
			->select( 'ufgf_priority' )
			->from( 'uf_group_flair' )
			->orderBy( 'ufgf_priority', 'ASC' )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();
		$priority = $min === null || $min === false ? 0 : ( (int)$min - 1 );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'uf_group_flair' )
			->row( [
				'ufgf_group' => $group,
				'ufgf_file' => $file,
				'ufgf_priority' => $priority,
				'ufgf_updated_at' => $now,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public function delete_group( string $group ): void {
		$group = trim( $group );
		if ( $group === '' ) {
			return;
		}
		$this->connection_provider->getPrimaryDatabase()
			->newDeleteQueryBuilder()
			->deleteFrom( 'uf_group_flair' )
			->where( [ 'ufgf_group' => $group ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param list<string> $groups_high_to_low
	 */
	public function save_order( array $groups_high_to_low ): void {
		$dbw = $this->connection_provider->getPrimaryDatabase();
		$now = $dbw->timestamp( ConvertibleTimestamp::now() );
		$known = [];
		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'ufgf_group' ] )
			->from( 'uf_group_flair' )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $res as $row ) {
			$known[(string)$row->ufgf_group] = true;
		}

		$count = count( $groups_high_to_low );
		$index = 0;
		foreach ( $groups_high_to_low as $group ) {
			$group = (string)$group;
			if ( $group === '' || !isset( $known[$group] ) ) {
				continue;
			}
			$priority = $count - $index;
			$index++;
			$dbw->newUpdateQueryBuilder()
				->update( 'uf_group_flair' )
				->set( [
					'ufgf_priority' => $priority,
					'ufgf_updated_at' => $now,
				] )
				->where( [ 'ufgf_group' => $group ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

}
