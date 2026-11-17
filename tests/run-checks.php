<?php
// Run the standalone checks without a WordPress database.
$checks = array_map( 'escapeshellarg', array_diff( glob( __DIR__ . '/*Check.php' ), array_map(
	static function ( $file ) { return __DIR__ . '/' . $file; },
	array( 'ClassIntegrationCheck.php', 'SchedulerIntegrationCheck.php', 'PluginBootstrapCheck.php' )
) ) );
foreach ( array( 'wpml', 'wpml5', 'plain' ) as $mode ) {
	$checks[] = escapeshellarg( __DIR__ . '/SharedProductsCheck.php' ) . ' ' . $mode;
}
foreach ( $checks as $command ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . $command, $status );
	if ( $status ) {
		exit( $status );
	}
}
