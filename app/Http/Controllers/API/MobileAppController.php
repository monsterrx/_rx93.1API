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
            $chart['song_artist'] = $chart->Song->Album->Artist->name;
            $chart['song_name'] = $chart->Song->name;
            $chart['album_image'] = $this->verifyPhoto($chart->Song->Album->image, 'albums');
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
        $charts = Chart::with('Song.Album.Artist')
            ->where('dated', $this->getLatestChartDate())
            ->where('daily', 0)
            ->where('is_posted', 1)
            ->where('location', $this->getStationCode())
            ->orderBy('position')
            ->get();

        foreach ($charts as $chart) {
            $chart->album_image = $this->verifyPhoto($chart->Song->Album->image, 'albums');
        }

        return response()->json([
            'charts' => $charts
        ]);
    }

    public function articles(Request $request) {
        $categories = Category::query()
            ->has('Article')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $articles = Article::with('Employee', 'Category')
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->where('location', $this->getStationCode())
            ->orderBy('created_at', 'desc')
            ->paginate(8);

        // for article filtering
        $category_id = $request->get('category_id');
        $keyword = $request->get('keyword');

        if($category_id) {
            try {
                $category = Category::query()
                    ->findOrFail($category_id);
            } catch (Exception $e) {
                return response()->json([
                    'error' => 'Category not found'
                ], 400);
            }

            $articles = Article::with('Employee')
                ->where('category_id', $category_id)
                ->latest()
                ->where('location', $this->getStationCode())
                ->whereNotNull('published_at')
                ->paginate(8)
                ->appends('category_id', $category_id);

            if ($keyword) {
                $articles = Article::with('Employee')
                    ->where('category_id', $category_id)
                    ->whereNotNull('published_at')
                    ->where('location', $this->getStationCode())
                    ->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('heading', 'like', '%'.$keyword.'%')
                    ->orWhereHas('Employee', function(Builder $query) use ($keyword) {
                        $query->where('first_name', 'like', '%'.$keyword.'%')
                            ->orWhere('last_name', 'like', '%'.$keyword.'%');
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate(8)
                    ->appends([
                        'category_id' => $category_id,
                        'keyword' => $keyword
                    ]);
            }

            foreach ($articles as $article) {
                $article->image = $this->verifyPhoto($article->image, 'articles');
                $article->author = $article->Employee->first_name . ' ' . $article->Employee->last_name;
                $article->time_passed = $this->timePassedSincePublished($article->published_at);
            }

            return response()->json([
                'category' => $category,
                'articles' => $articles,
                'next' => $articles->nextPageUrl()
            ]);
        }

        foreach ($articles as $article) {
            $article->image = $this->verifyPhoto($article->image, 'articles');
            $article->author = $article->Employee->first_name . ' ' . $article->Employee->last_name;
            $article->time_passed = $this->timePassedSincePublished($article->published_at);
        }

        return response()->json([
            'categories' => $categories,
            'articles' => $articles,
            'next' => $articles->nextPageUrl()
        ]);
    }

    public function viewArticle($id) {
        try {
            $article = Article::query()
                ->with('Employee', 'Category' ,'Content', 'Relevant')
                ->findOrFail($id);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Article not found'
            ], 400);
        }

        $article->image = $this->verifyPhoto($article->image, 'articles');

        $article->update_date = date('F d, Y', strtotime($article->updated_at));
        $article->publish_date = date('F d, Y', strtotime($article->published_at));
        $article->time_passed = $this->timePassedSincePublished($article->published_at);
        $article->author = $article->Employee->first_name . ' ' . $article->Employee->last_name;

        foreach ($article->Content as $articleContent) {
            $articleContent->content = $this->replaceSourceText($articleContent->content);
            $articleContent->content = $this->replaceArticleSourceText($articleContent->content);
        }

        return response()->json([
            'article' => $article
        ]);
    }

    public function podcasts(Request $request) {
        $shows = Show::has('Podcast')
            ->with('Jock')
            ->whereNull('deleted_at')
            ->where('location', $this->getStationCode())
            ->orderBy('title')
            ->get();

        $podcasts = Podcast::with('Show.Jock')
            ->whereNull('deleted_at')
            ->where('location', $this->getStationCode())
            ->orderBy('created_at', 'desc')
            ->paginate(8);

        // for podcast filtering
        $show_id = $request->get('show_id');
        $keyword = $request->get('keyword');

        if($show_id) {
            $podcasts = Podcast::query()
                ->with('Show.Jock')
                ->whereNull('deleted_at')
                ->where('show_id', $show_id)
                ->where('location', $this->getStationCode())
                ->orderBy('created_at', 'desc')
                ->paginate(8)
                ->appends('show_id', $show_id);

            if ($keyword) {
                $podcasts = Podcast::query()
                    ->with('Show.Jock')
                    ->whereNull('deleted_at')
                    ->where('show_id', $show_id)
                    ->where('episode', 'like', '%'.$keyword.'%')
                    ->where('location', $this->getStationCode())
                    ->orderBy('created_at', 'desc')
                    ->paginate(8)
                    ->appends([
                        'show_id' => $show_id,
                        'keyword' => $keyword
                    ]);
            }

            $show = Show::query()
                ->with('Jock')
                ->findOrFail($show_id);

            $show->icon = $this->verifyPhoto($show->icon, 'shows');
            $show->background_image = $this->verifyPhoto($show->background_image, 'shows');
            $show->header_image = $this->verifyPhoto($show->header_image, 'shows');

            foreach ($podcasts as $podcast) {
                $podcast['date'] = date('M d, Y', strtotime($podcast['created_at']));
                $podcast['time_elapsed'] = $this->timePassedSincePublished($podcast['date']);
                $podcast['short_episode'] = Str::limit($podcast['episode'], 16);
                $podcast['image'] = $this->verifyPhoto($podcast['image'], 'podcasts');
            }

            $podcasts->appends('show_id', $show_id);

            return response()->json([
                'show' => $show,
                'podcasts' => $podcasts
            ]);
        }

        foreach ($podcasts as $podcast) {
            $podcast['date'] = date('M d, Y', strtotime($podcast['created_at']));
            $podcast['time_elapsed'] = $this->timePassedSincePublished($podcast['date']);
            $podcast['short_episode'] = Str::limit($podcast['episode'], 16);
            $podcast['image'] = $this->verifyPhoto($podcast['image'], 'podcasts');
        }

        foreach ($shows as $show) {
            $show->background_image = $this->verifyPhoto($show->background_image, 'shows');
            $show->icon = $this->verifyPhoto($show->icon, 'shows');
            $show->header_image = $this->verifyPhoto($show->header_image, 'shows');
        }

        return response()->json([
            'shows' => $shows,
            'podcasts' => $podcasts,
            'next' => $podcasts->nextPageUrl() // to be removed when the mobile app is released
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
                ->paginate(5)
                ->appends('keyword', $search);

            $articles = Article::whereNull('deleted_at')
                ->whereNotNull('published_at')
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('heading', 'like', '%'.$search.'%')
                ->orderByDesc('created_at')
                ->paginate(5)
                ->appends('keyword', $search);
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

    public function shows() {
        $shows = Show::query()
            ->with('Jock')
            ->get();

        foreach ($shows as $show) {
            $show->background_image = $this->verifyPhoto($show->background_image, 'shows');
            $show->icon = $this->verifyPhoto($show->icon, 'shows');
            $show->header_image = $this->verifyPhoto($show->header_image, 'shows');
        }

        return response()->json([
            'shows' => $shows
        ]);
    }

    public function viewShow($id) {
        $show = Show::query()
            ->with(['Podcast', 'Jock'])
            ->findOrFail($id);

        $show->background_image = $this->verifyPhoto($show->background_image, 'shows');
        $show->icon = $this->verifyPhoto($show->icon, 'shows');
        $show->header_image = $this->verifyPhoto($show->header_image, 'shows');

        foreach ($show->Podcast as $podcast) {
            $podcast->image = $this->verifyPhoto($podcast->image, 'podcasts');
        }

        return response()->json([
            'show' => $show
        ]);
    }
}
