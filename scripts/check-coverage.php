<?php

$path = $argv[1] ?? __DIR__.'/../coverage/clover.xml';
$minimum = (float) ($argv[2] ?? 50);
if (!is_file($path)) {
    fwrite(STDERR, "Missing coverage report: {$path}\n");
    exit(1);
}
$xml = simplexml_load_file($path);
$metrics = $xml->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
if (!$total) {
    fwrite(STDERR, "Coverage report has no executable lines.\n");
    exit(1);
}
$percentage = 100 * $covered / $total;
printf("Application line coverage: %.2f%% (%d/%d); minimum %.2f%%\n", $percentage, $covered, $total, $minimum);
exit($percentage >= $minimum ? 0 : 1);
