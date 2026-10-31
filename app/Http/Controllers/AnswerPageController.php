<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Support\AnswerPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnswerPageController extends Controller
{
    public function national(): View
    {
        return view('public.answers.show', AnswerPages::weekend(null, false));
    }

    public function provinceThis(string $province): View
    {
        return view('public.answers.show', AnswerPages::weekend($this->province($province), false));
    }

    public function provinceNext(string $province): View
    {
        return view('public.answers.show', AnswerPages::weekend($this->province($province), true));
    }

    public function discipline(Discipline $discipline, string $province): RedirectResponse
    {
        $this->province($province);

        return redirect()->route('disciplines.province', [$discipline->slug, $province], 301);
    }

    private function province(string $slug): Province
    {
        return Province::fromUrlSlug($slug) ?? abort(404);
    }
}
