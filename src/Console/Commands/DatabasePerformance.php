<?php

namespace Timekit\Performance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\Table;
use Timekit\Performance\QueryContainer;

class DatabasePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:db {id?} {compare?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var string
     */
    private $folder;
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->folder = storage_path('performance');
        $this->filesystem = $filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('id');
        $compareTo = $this->argument('compare');

        if ($name === null && $compareTo === null) {
            $this->listRequests();

            return;
        }

        if ($name !== null && $compareTo !== null) {
            $this->info(sprintf('compare: %s and %s', $name, $compareTo));
            $this->compare($name, $compareTo);

            return;
        }

        $this->info('Showing info for request ' . $name);
        $this->showRequest($name);
    }

    private function listRequests()
    {
        $table = new Table($this->output);
        $table->setHeaders(['id', 'timestamp', 'url', 'select queries', 'insert queries', 'delete queries', 'update queries', 'total time']);
        $files = $this->filesystem->files($this->folder);
        $requests = collect();
        foreach ($files as $file) {
            $shortName = last(explode('/', $file));
            $obj = $this->loadRequest($shortName);
            $requests[$shortName] = $obj;
        }

        $requests->sortByDesc(function(QueryContainer $container){
            return $container->getTimestamp();
        })->each(function(QueryContainer $obj, $key) use ($table){
            $table->addRow([$key, $obj->getTimestamp(), $obj->getUrl(), $obj->getSelectCount(), $obj->getInsertCount(), $obj->getDeleteCount(), $obj->getUpdateCount(), $obj->getTotalSQLTime()]);
        });

        $table->render();
    }

    private function compare($name, $compareTo)
    {
        $a = $this->loadRequest($name);
        $b = $this->loadRequest($compareTo);

        if ($a->getUrl() !== $b->getUrl()) {
            $this->error('You have to compare two request to the same url');
        }

        $table = new Table($this->output);
        $table->setHeaders(['id', 'timestamp', 'select queries', 'insert queries', 'delete queries', 'update queries', 'total queries', 'total time']);
        $table->addRow([$name, $a->getTimestamp(), $a->getSelectCount(), $a->getInsertCount(), $a->getDeleteCount(), $a->getUpdateCount(), $a->getTotalCount(), $a->getTotalSQLTime()]);
        $table->addRow([$compareTo, $b->getTimestamp(), $b->getSelectCount(), $b->getInsertCount(), $b->getDeleteCount(), $b->getUpdateCount(), $b->getTotalCount(), $b->getTotalSQLTime()]);
        $table->addRow(['Diff: ',
            ($b->getTimestamp()->diffForHumans($a->getTimestamp())),
            $this->upOrDown($a->getSelectCount(), $b->getSelectCount()),
            $this->upOrDown($a->getInsertCount(), $b->getInsertCount()),
            $this->upOrDown($a->getDeleteCount(), $b->getDeleteCount()),
            $this->upOrDown($a->getUpdateCount(), $b->getUpdateCount()),
            $this->upOrDown($a->getTotalCount(), $b->getTotalCount()),
            $this->upOrDown($a->getTotalSQLTime(), $b->getTotalSQLTime()),
        ]);

        $aQueries = $a->sortBy('query', 'count');
        $bQueries = $b->sortBy('query', 'count');

        $table->render();

        $table = new Table($this->output);
        $table->setHeaders(['query', 'count a', 'count b', 'diff', 'sum a', 'sum b', 'diff', 'avg a', 'avg b', 'diff']);

        $aQueries->each(function ($item, $key) use ($bQueries, $table) {
            $compareTo = $bQueries->get($key);
            unset($bQueries[$key]);
            $table->addRow(
                [
                    $item[0]['query'],
                    $item['count'],
                    $compareTo['count'],
                    $this->upOrDown($item['count'], $compareTo['count']),
                    $this->prettyPrint($item['sum']),
                    $this->prettyPrint($compareTo['sum']),
                    $this->upOrDown($item['sum'], $compareTo['sum']),
                    $this->prettyPrint($item['avg']),
                    $this->prettyPrint($compareTo['avg']),
                    $this->upOrDown($item['avg'], $compareTo['avg']),
                ]
            );
        });

        $bQueries->each(function ($item, $key) use ($table) {
            $table->addRow(
                [
                    $item[0]['query'],
                    0,
                    $item['count'],
                    $this->upOrDown(0, $item['count']),

                ]
            );
        });

        $table->render();
    }

    private function showRequest($name)
    {
        $obj = $this->loadRequest($name);
        $table = new Table($this->output);
        $table->setHeaders(['id', 'timestamp', 'url', 'select queries', 'insert queries', 'delete queries', 'update queries']);

        $table->addRow([$name, $obj->getTimestamp(), $obj->getUrl(), $obj->getSelectCount(), $obj->getInsertCount(), $obj->getDeleteCount(), $obj->getUpdateCount()]);
        $table->render();

        $this->displayStats($obj, 'query', 'avg');
        $this->displayStats($obj, 'query', 'count');
        $this->displayStats($obj, 'query', 'sum');
    }

    private function loadRequest($file):QueryContainer
    {
        $content = $this->filesystem->get($this->folder . '/' . $file);
        /** @var QueryContainer $obj */
        $obj = unserialize($content);

        return $obj;
    }

    private function displayStats(QueryContainer $obj, $groupBy, $sortBy)
    {
        $this->info(sprintf('Queries grouped by %s sorted by %s', $groupBy, $sortBy));
        $this->showStats($obj, $groupBy, $sortBy);
    }

    private function showStats(QueryContainer $obj, $groupBy, $sortBy)
    {
        $table = new Table($this->output);
        $queries = $obj->sortBy($groupBy, $sortBy);
        $table->setHeaders(['query', 'count', 'sum (ms)', 'avg', 'percent']);
        /** @var Collection $query */
        foreach ($queries as $query) {
            $q = $query[0];
            $table->addRow([Str::limit($q['query'], 1000), $query['count'], $query['sum'], $query['avg'], $query['percent']]);
        }
        $table->addRow(['Total', $queries->sum('count'), $queries->sum('sum'), $queries->sum('percent')]);
        $table->render();
    }

    private function upOrDown($a, $b)
    {
        $style = null;
        $indicator = '→';
        if ($a > $b) {
            $indicator = '↓';
            $style = 'info';
        }

        if ($a < $b) {
            $indicator = '↑';
            $style = 'error';
        }

        if ($style === null) {
            return sprintf("%s %s", $indicator, abs($a - $b));
        }

        return sprintf("<%s>%s %s</%s>", $style, $indicator, round(abs($a - $b), 2), $style);

    }

    private function prettyPrint(float $a)
    {
        return round($a, 2);
    }
}
