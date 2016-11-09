<?php

namespace Timekit\Performance;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QueryContainer
{
    const FULL_QUERY = 'fullQuery';
    const QUERY = 'query';
    const SUM = 'sum';
    const COUNT = 'count';
    const TIME = 'time';
    const TYPE = 'type';
    const AVG = 'avg';

    /**
     * @var Collection
     */
    private $queries;
    /**
     * @var float
     */
    private $slowQueryThreshold;
    /**
     * @var Request
     */
    private $request;
    private $params;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var string
     */
    private $storagePath;

    public function __construct(Request $request = null, Filesystem $filesystem, string $storagePath)
    {
        $this->resetCounters();
        $this->slowQueryThreshold = 10;
        $this->timestamp = Carbon::now();
        $this->url = $request->getPathInfo();
        $this->method = $request->getMethod();
        $this->filesystem = $filesystem;
        $this->request = $request;
        if (!Str::endsWith($storagePath, '/')){
            throw new \RuntimeException('Storage path must end with a /');
        }
        $this->storagePath = $storagePath;
        $this->fileName = Str::random();
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @return Collection
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return float
     */
    public function getSlowQueryThreshold(): float
    {
        return $this->slowQueryThreshold;
    }

    public function save()
    {
        $fullPath = $this->storagePath . $this->fileName;
        return $this->filesystem->put(
            $fullPath,
            serialize($this)
        );
    }

    public function addByEvent(QueryExecuted $event)
    {
        $sql = str_replace(['%', '?'], ['%%', '%s'], $event->sql);
        $fullQuery = vsprintf($sql, $event->bindings);
        $time = $event->time;

        $this->add($event->sql, $time, $fullQuery);
    }

    public function add($sql, $time, $fullQuery = null)
    {
        $type = $this->detectQueryType($sql);
        $this->queries->push([
            self::FULL_QUERY => $fullQuery,
            self::QUERY      => $sql,
            self::TIME       => $time,
            self::TYPE       => $type
        ]);
    }

    public function resetCounters()
    {
        $this->queries = collect();
    }

    public function detectQueryType($query)
    {
        $query = strtolower($query);
        if (Str::startsWith($query, 'select')) {
            return 'select';
        }

        if (Str::startsWith($query, 'insert')) {
            return 'insert';
        }

        if (Str::startsWith($query, 'delete')) {
            return 'delete';
        }

        if (Str::startsWith($query, 'update')) {
            return 'update';
        }

        return 'misc';
    }

    public function allQueries()
    {
        return $this->prettyPrint($this->queries);
    }

    private function prettyPrint(Collection $collection)
    {
        return $collection->map(function ($item) {
            return sprintf('[%s ms]: %s', $item[self::TIME], $item[self::QUERY]);
        });
    }

    public function getTotalSQLTime()
    {
        return $this->queries->sum(self::TIME);
    }

    public function sortBy($groupBy, $sortBy): Collection
    {
        $allowedGroupBy = [self::FULL_QUERY, self::QUERY];
        if (!in_array($groupBy, $allowedGroupBy)) {
            throw new InvalidArgumentException(sprintf("groupBy must be one of %s", print_r($allowedGroupBy, true)));
        }

        $allowedSortBy = [self::COUNT, self::SUM, self::AVG];
        if (!in_array($sortBy, $allowedSortBy)) {
            throw new InvalidArgumentException(sprintf("sortBy must be one of %s", print_r($allowedSortBy, true)));
        }

        $list = $this->queries->groupBy($groupBy);

        $list = $this->indexQueries($list);

        return $list->sortByDesc($sortBy);
    }

    public function getTotalSQLTimePerType()
    {
        $total = $this->getTotalSQLTime();
        $types = $this->queries->groupBy(self::TYPE);
        $output = [];
        foreach ($types as $name => $type) {
            $sum = $type->sum(self::TIME);
            $output[$name] = [
                'sum'     => $sum,
                'percent' => ($sum / $total) * 100,
                'avg'     => $sum / $this->getCount($name),
                'count'   => $this->getCount($name),
            ];
        }

        $output['total'] = [
            'sum'     => $total,
            'percent' => ($total / $total) * 100,
            'count'   => $this->getTotalCount(),
            'avg'     => $total / $this->getTotalCount(),
            'max'     => $this->getSlowestQuery()
        ];

        return $output;
    }

    public function allSlowQueries()
    {
        return $this->prettyPrint($this->getSlowQueries());
    }

    public function allCount()
    {
        return $this->queries->count();
    }

    public function slowQueryCount()
    {
        return $this->getSlowQueries()->count();
    }

    private function getSlowQueries():Collection
    {
        return $this->queries->where('time', '>', $this->slowQueryThreshold);
    }

    public function getSlowestQuery()
    {
        return $this->queries->max(self::TIME);
    }

    public function getSelectCount()
    {
        return $this->getCount('select');
    }

    public function getCount($type)
    {
        return $this->queries->where(self::TYPE, '=', $type)->count();
    }

    public function getInsertCount()
    {
        return $this->getCount('insert');
    }

    public function getDeleteCount()
    {
        return $this->getCount('delete');
    }

    public function getUpdateCount()
    {
        return $this->getCount('update');
    }

    public function getMiscCount()
    {
        return $this->getCount('misc');
    }

    public function getTotalCount()
    {
        return $this->queries->count();
    }

    private function indexQueries($list):Collection
    {
        $total = $this->getTotalSQLTime();

        return $list->each(function (Collection $item, $key) use ($total) {
            $subSum = $item->sum('time');
            $item['count'] = $item->count();
            $item['sum'] = $subSum;
            $item['percent'] = ($subSum / $total) * 100;
            $item['avg'] = $subSum / $item['count'];
            $item['total'] = $total;
        });
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }
}
