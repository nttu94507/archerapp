<?php

namespace App\Http\Controllers;

use App\Models\TeamPost;
use Illuminate\Http\Request;

class TeamPostController extends Controller
{
    //
    public function __construct()
    {
        // 列表 & 單篇 everyone 都可以看，發文需要登入
//        $this->middleware('auth')->only(['create', 'store']);
    }

    public function index()
    {
        $posts = TeamPost::latest()->paginate(10);

        return view('team_posts.index', compact('posts'));
    }

    public function create()
    {
        return view('team_posts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $data['user_id'] = auth()->id(); // 如果沒 auth，可以拿掉這行 + 資料表欄位

        TeamPost::create($data);

        return redirect()
            ->route('team-posts.index')
            ->with('success', '組隊貼文已建立！');
    }

    public function show(TeamPost $teamPost)
    {
        return view('team_posts.show', compact('teamPost'));
    }
}
