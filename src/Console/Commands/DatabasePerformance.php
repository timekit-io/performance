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
        $table->setHeaders(['id', 'timestamp', 'url', 'select queries', 'insert queries', 'delete queries', 'update queries']);
        $files = $this->filesystem->files($this->folder);
        foreach ($files as $file) {
            $shortName = last(explode('/', $file));
            $obj = $this->loadRequest($shortName);
            $table->addRow([$shortName, $obj->getTimestamp(), $obj->getUrl(), $obj->getSelectCount(), $obj->getInsertCount(), $obj->getDeleteCount(), $obj->getUpdateCount()]);
        }

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
        $table->setHeaders(['id', 'timestamp', 'select queries', 'insert queries', 'delete queries', 'update queries']);
        $table->addRow([$name, $a->getTimestamp(), $a->getSelectCount(), $a->getInsertCount(), $a->getDeleteCount(), $a->getUpdateCount()]);
        $table->addRow([$compareTo, $b->getTimestamp(), $b->getSelectCount(), $b->getInsertCount(), $b->getDeleteCount(), $b->getUpdateCount()]);
        $table->addRow(['Diff: ',
            ($a->getTimestamp()->diffForHumans($b->getTimestamp())),
            ($a->getSelectCount() - $b->getSelectCount()),
            ($a->getInsertCount() - $b->getInsertCount()),
            ($a->getDeleteCount() - $b->getDeleteCount()),
            ($a->getUpdateCount() - $b->getUpdateCount())
        ]);

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
            $table->addRow([Str::limit($q['query'], 100), $query['count'], $query['sum'], $query['avg'], $query['percent']]);
        }
        $table->addRow(['Total', $queries->sum('count'), $queries->sum('sum'), $queries->sum('percent')]);
        $table->render();
    }
}
