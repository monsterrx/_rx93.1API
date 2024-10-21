<?php

namespace App\Http\Controllers\API;

use App\Models\Title;
use App\Traits\AssetProcessors;
use App\Traits\ChartFunctions;
use App\Traits\JockFunctions;
use App\Traits\MediaProcessors;
use App\Traits\SystemFunctions;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Exception;
use Str;
use Carbon\Carbon;
use App\Models\Show;
use App\Models\Chart;
use App\Models\Article;
use App\Models\Contest;
use App\Models\Podcast;
use App\Models\Timeslot;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MobileAppController extends Controller
{
    use SystemFunctions, JockFunctions, AssetProcessors, MediaProcessors, ChartFunctions;

    public function home(Request $request) {
        $day = Carbon::now()->format('l');
        $time = date('H:i');

        $session_id = $this->IdGenerator(10);

        $giveaway = Contest::whereNull('deleted_at')
            ->orderBy('type')
            ->where('is_active', 1)
            ->where('location', $this->getStationCode())
            ->get();

        $charts = Chart::with('Song.Album.Artist')
            ->whereNull('deleted_at')
            ->where('dated', $this->getLatestChartDate())
            ->where('daily', 0)
            ->where('is_posted', 1)
            ->where('location', $this->getStationCode())
            ->orderBy('position')
            ->get()
            ->take(5);

        $articles = Article::with('Employee')
            ->latest()
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->where('location', $this->getStationCode())
            ->orderBy('published_at', 'desc')
            ->get()
            ->take(5);

        $podcasts = Podcast::with('Show')
            ->latest()
            ->whereNull('deleted_at')
            ->where('show_id', 1) // The Morning Rush
            ->where('location', $this->getStationCode())
            ->get()
            ->take(5);

        $currentShow = Show::with('Timeslot')
            ->whereHas('Timeslot', function($query) {
                $day = Carbon::now()->format('l');
                $time = date('H:i');

                $query->whereNull('deleted_at')
                    ->where('end', '>', $time)
                    ->where('start', '<=', $time)
                    ->where('day', '=', $day)
                    ->where('location', $this->getStationCode())
                    ->orderBy('start');
            })->whereNull('deleted_at')
            ->first();

        $shows = Show::has('Podcast')
            ->where('location', $this->getStationCode())
            ->get();

        $chart_date = date('F d, Y', strtotime($charts->first()->dated));

        $jocks = $this->jocksQuery($time, $day);

        foreach ($charts as $chart) {
            $chart->Song->Album->image = $this->verifyPhoto($chart->Song->Album->image, 'albums');
        }

        foreach ($articles as $article) {
            $article->image = $this->verifyPhoto($article['image'], 'articles');
        }

        foreach ($podcasts as $podcast) {
            $podcast->image = $this->verifyPhoto($podcast['image'], 'podcasts');
        }

        foreach ($jocks as $jock) {
            $jock->profile_image = $this->verifyPhoto($jock->profile_image, 'jocks');
        }

        foreach ($shows as $show) {
            $show->background_image = $this->verifyPhoto($show->background_image, 'shows');

            $show->icon = $this->verifyPhoto($show->icon, 'shows');

            $show->header_image = $this->verifyPhoto($show->header_image, 'shows');
        }

        if($currentShow) {
            $currentShow->background_image = $this->verifyPhoto($currentShow->background_image, 'shows');

            return response()->json([
                'giveaways' => $giveaway,
                'charts' => $charts,
                'articles' => $articles,
                'podcasts' => $podcasts,
                'shows' => $shows,
                'chart_date' => $chart_date,
                'jocks' => $jocks,
                'session_id' => $session_id
            ]);
        }

        return response()->json([
            'giveaways' => $giveaway,
            'charts' => $charts,
            'articles' => $articles,
            'podcasts' => $podcasts,
            'shows' => $shows,
            'chart_date' => $chart_date,
            'show' => $currentShow,
            'jocks' => $jocks,
            'session_id' => $session_id
        ]);
    }

    public function live() {
        // Variables on getting the day and time
        $day = Carbon::now()->format('l');
        $time = date('H:i');

        // 20240924 Update
        $stream = $this->getStream();

        $currentShow = Show::query()
            ->whereHas('Timeslot', function($query) use ($day, $time) {
                $query->whereNull('deleted_at')
                    ->where('end', '>', $time)
                    ->where('start', '<=', $time)
                    ->where('day', '=', $day)
                    ->where('location', $this->getStationCode())
                    ->orderBy('start');
            })->whereNull('deleted_at')
            ->first();

        if ($currentShow == null) {
            return response()->json(['show' => $currentShow, 'live' => $stream]);
        }

        $currentShow['background_image'] = $this->verifyPhoto($currentShow['background_image'], 'shows');
        $currentShow['icon'] = $this->verifyPhoto($currentShow['icon'], 'shows');
        $currentShow['header_image'] = $this->verifyPhoto($currentShow['header_image'], 'shows');

        $showList = Timeslot::with('Show')
            ->whereNull('deleted_at')
            ->where('day', $day)
            ->where('location', $this->getStationCode())
            ->orderBy('start')
            ->get();

        foreach ($showList as $timeslot) {
            $timeslot['start'] = date('h:i A', strtotime($timeslot['start']));
            $timeslot['end'] = date('h:i A', strtotime($timeslot['end']));
        }

        $currentJocks = $this->jocksQuery($time, $day);

        foreach ($currentJocks as $jock) {
            $jock->profile_image = $this->verifyPhoto($jock->profile_image, 'jocks');
            $jock->background_image = $this->verifyPhoto($jock->background_image, 'jocks');
            $jock->main_image = $this->verifyPhoto($jock->main_image, 'jocks');
        }

        return response()->json([
            'show' => $currentShow,
            'jocks' => $currentJocks,
            'live' => $stream
        ]);
    }

    public function charts() {
        $charts = Chart::with('Song.Album.Artist')->where('dated', $this->getLatestChartDate())
            ->where('daily', 0)
            ->where('is_posted', 1)
            ->where('location', $this->getStationCode())
            ->orderBy('position')
            ->get();

        foreach ($charts as $chart) {
            $chart->Song->Album->image = $this->verifyPhoto($chart->Song->Album->image, 'albums');
        }

        return response()->json([
            'charts' => $charts
        ]);
    }

    public function articles(Request $request) {
        $categories = Category::has('Article')->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $articles = Article::with('Employee', 'Category')
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->where('location', $this->getStationCode())
            ->orderBy('created_at', 'desc')
            ->paginate(8);

        // for article filtering
        $category_id = $request['category_id'];

        if($category_id) {
            $articles = Article::with('Employee', 'Category')
                ->where('category_id', $category_id)
                ->whereNotNull('published_at')
                ->where('location', $this->getStationCode())
                ->orderBy('created_at', 'desc')
                ->paginate(8);
        }

        foreach ($articles as $article) {
            $article->image = $this->verifyPhoto($article->image, 'articles');
            $article->author = $article->Employee->first_name . ' ' . $article->Employee->last_name;
        }

        return response()->json([
            'categories' => $categories,
            'articles' => $articles,
            'next' => $articles->nextPageUrl()
        ]);
    }

    public function viewArticle($id) {
        $article = Article::with('Employee', 'Category' ,'Content', 'Relevant')->findOrFail($id);

        $article->image = $this->verifyPhoto($article->image, 'articles');

        $article->update_date = date('F d, Y', strtotime($article->updated_at));
        $article->publish_date = date('F d, Y', strtotime($article->published_at));
        $article->time_passed = $this->timePassedSincePublished($article->published_at);
        $article->author = $article->Employee->first_name . ' ' . $article->Employee->last_name;

        return response()->json([
            'article' => $article
        ]);
    }

    public function podcasts(Request $request) {
        $shows = Show::has('Podcast')->whereNull('deleted_at')
            ->where('location', $this->getStationCode())
            ->orderBy('title')
            ->get();

        $podcasts = Podcast::with('Show')
            ->whereNull('deleted_at')
            ->where('location', $this->getStationCode())
            ->orderBy('created_at', 'desc')
            ->simplePaginate(8);

        // for podcast filtering
        $show_id = $request['show_id'];

        if($show_id) {
            $podcasts = Podcast::with('Show')
                ->whereNull('deleted_at')
                ->where('show_id', $show_id)
                ->where('location', $this->getStationCode())
                ->orderBy('created_at', 'desc')
                ->simplePaginate(8);

            $podcasts->appends('show_id', $show_id);
        }

        foreach ($podcasts as $podcast) {
            $podcast['episode'] = Str::limit($podcast['episode'], 16);
            $podcast['image'] = $this->verifyPhoto($podcast['image'], 'podcasts');
        }

        return response()->json([
            'shows' => $shows,
            'podcasts' => $podcasts,
            'next' => $podcasts->nextPageUrl()
        ]);
    }

    public function viewPodcast($id) {
        $podcast = Podcast::with('Show')->findOrFail($id);

        $podcast['image'] = $this->verifyPhoto($podcast['image'], 'podcasts');
        $podcast['show']['background_image'] = $this->verifyPhoto($podcast['show']['background_image'], 'shows');

        return response()->json([
            'podcast' => $podcast
        ]);
    }

    public function youTube($max) {
        $channel_id = 'UCMgKa-bzBoj40sQUqvX7kag';
        $channel_key = 'AIzaSyDv1JDmiKR1QiLeKaUIWtWTA45Ay-cUmyk'; // AIzaSyAr-GrOSqC0o3-d-DPbkUM4E7kOZ76KRNA
        $channel_max = $max;

        $data = array('id' => $channel_id, 'key' => $channel_key, 'max' => $channel_max);

        return response()->json($data);
    }

    public function search(Request $request) {
        return view('components.search');
    }

    public function browse(Request $request) {
        $search = $request->keyword;

        $podcasts = Podcast::with('Show')
            ->whereNull('deleted_at')
            ->where('location', $this->getStationCode())
            ->orderBy('date', 'desc')
            ->get()
            ->take(5);

        $articles = Article::with('Category', 'Employee')
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->take(5);

        if($search) {
            $podcasts = Podcast::with('Show')
                ->whereNull('deleted_at')
                ->where('episode', 'like', '%'.$search.'%')
                ->orderByDesc('created_at')
                ->simplePaginate(5);

            $articles = Article::whereNull('deleted_at')
                ->whereNotNull('published_at')
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('heading', 'like', '%'.$search.'%')
                ->orderByDesc('created_at')
                ->simplePaginate(5);
        }

        foreach ($podcasts as $podcast) {
            $podcast['image'] = $this->verifyPhoto($podcast['image'], 'podcasts');
        }

        foreach ($articles as $article) {
            $article['image'] = $this->verifyPhoto($article['image'], 'articles');
        }

        return response()->json([
            'podcasts' => $podcasts,
            'articles' => $articles
        ]);
    }

    public function help() {
        return view('help');
    }

    public function about() {
        return view('about');
    }

    public function vote(Request $request) {
        $chart_id = $request->chart_id;

        $chart = Chart::with('Song.Album.Artist')->findOrFail($chart_id);

        $chart->voted_at = date('Y-m-d');
        ++$chart->phone_votes;
        $chart->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Vote Sent!'
        ]);
    }

    public function assets($id) {
        $title = Title::with('Asset')->findOrFail($id);

        $title->Asset->logo = $this->verifyPhoto($title->Asset->logo, '_assets/mobile');
        $title->Asset->chart_icon = $this->verifyPhoto($title->Asset->chart_icon, '_assets/mobile');
        $title->Asset->article_icon = $this->verifyPhoto($title->Asset->article_icon, '_assets/mobile');
        $title->Asset->podcast_icon = $this->verifyPhoto($title->Asset->podcast_icon, '_assets/mobile');
        $title->Asset->article_page_icon = $this->verifyPhoto($title->Asset->article_page_icon, '_assets/mobile');
        $title->Asset->youtube_page_icon = $this->verifyPhoto($title->Asset->youtube_page_icon, '_assets/mobile');

        return response()->json([
            'title' => $title
        ]);
    }

    public function show($id) {
        $podcasts = Podcast::with('Show')
            ->where('show_id', $id)
            ->orderByDesc('created_at')
            ->simplePaginate(15);

        foreach ($podcasts as $podcast) {
            $podcast->image = $this->verifyPhoto($podcast->image, 'podcasts');
        }

        $podcast->Show->background_image = $this->verifyPhoto($podcast->Show->background_image, 'shows');

        $podcast->Show->icon = $this->verifyPhoto($podcast->Show->icon, 'shows');

        $podcast->Show->header_image = $this->verifyPhoto($podcast->Show->header_image, 'shows');

        return response()->json([
            'podcasts' => $podcasts
        ]);
    }
}
