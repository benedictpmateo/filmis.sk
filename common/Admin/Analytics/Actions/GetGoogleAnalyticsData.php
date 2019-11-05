<?php

namespace Common\Admin\Analytics\Actions;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelAnalytics\LaravelAnalytics;

class GetGoogleAnalyticsData implements GetAnalyticsData
{
    /**
     * @var LaravelAnalytics
     */
    private $analytics;

    /**
     * @param LaravelAnalytics $analytics
     */
    public function __construct(LaravelAnalytics $analytics)
    {
        $this->analytics = $analytics;
        $this->registerCollectionMacros();
    }

    public function execute()
    {
        return [
            'browsers' =>  $this->analytics->getTopBrowsers(7),
            'countries' => $this->getCountries(),
            // 'weeklyPageViews' => $this->weeklyPageViews(),
            'monthlyAudience' => $this->monthlyAudience(),
            'monthlyPageViews' => $this->monthlyPageViews(),
        ];
    }

    private function weeklyPageViews()
    {
        return [
            'current' => $this->getPageViews(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()),
            'previous' => $this->getPageViews(Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek())
        ];
    }

    private function monthlyPageViews()
    {
        return [
            'current' => $this->getPageViews(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            'previous' => $this->getPageViews(Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth())
        ];
    }
    private function monthlyAudience()
    {
        return [
            'current' => $this->getAudienceOverview(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            'previous' => $this->getAudienceOverview(Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth())
        ];
    }

    private function getAudienceOverview(Carbon $start, Carbon $end) {
        $data = $this->analytics->performQuery(
            $start,
            $end,
            'ga:users',
            ['dimensions' => 'ga:date']
        );

        if (is_null($data->rows)) {
            return new Collection([]);
        }

        $rows = [];
        foreach ($data->rows as $row) {
            $rows[] = [
                'pageViews' => (int)$row[1],
                'date' => $this->transformDate($row[0])
            ];
        }
        return $rows;
    }

    private function transformDate($date) {
        return strtotime(substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2));
    }

    private function getPageViews(Carbon $start, Carbon $end)
    {
        $data = $this->analytics->getVisitorsAndPageViewsForPeriod($start, $end);

        return $data->map(function($item) {
            return [
                'pageViews' => $item['pageViews'],
                'date' => $item['date']->getTimestamp()
            ];
        });
    }

    private function getCountries($maxResults = 6)
    {
        $answer = $this->analytics->performQuery(
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
            'ga:sessions',
            ['dimensions' => 'ga:country', 'sort' => '-ga:sessions']
        );

        if (is_null($answer->rows)) {
            return new Collection([]);
        }

        $pagesData = [];
        foreach ($answer->rows as $pageRow) {
            $pagesData[] = ['country' => $pageRow[0], 'sessions' => $pageRow[1]];
        }

        $countries = new Collection(array_slice($pagesData, 0, $maxResults - 1));

        if (count($pagesData) > $maxResults) {
            $otherCountries = new Collection(array_slice($pagesData, $maxResults - 1));
            $otherCountriesCount = array_sum(Collection::make($otherCountries->lists('sessions'))->toArray());

            $countries->put(null, ['country' => 'other', 'sessions' => $otherCountriesCount]);
        }

        return $countries;
    }

    private function registerCollectionMacros()
    {
        // laravel analytics page needs legacy "lists" method
        Collection::macro('lists', function($value, $key = null) {
            return self::pluck($value, $key);
        });
    }
}