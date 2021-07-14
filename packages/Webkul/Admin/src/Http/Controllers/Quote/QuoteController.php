<?php

namespace Webkul\Admin\Http\Controllers\Quote;

use Illuminate\Support\Facades\Event;
use Barryvdh\DomPDF\Facade as PDF;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Http\Requests\AttributeForm;
use Webkul\Quote\Repositories\QuoteRepository;
use Webkul\Lead\Repositories\LeadRepository;

class QuoteController extends Controller
{
    /**
     * QuoteRepository object
     *
     * @var \Webkul\Quote\Repositories\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * LeadRepository object
     *
     * @var \Webkul\Lead\Repositories\LeadRepository
     */
    protected $leadRepository;

    /**
     * Create a new controller instance.
     *
     * @param \Webkul\Quote\Repositories\QuoteRepository  $quoteRepository
     * @param \Webkul\Lead\Repositories\LeadRepository  $leadRepository
     *
     * @return void
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        LeadRepository $leadRepository
    )
    {
        $this->quoteRepository = $quoteRepository;

        $this->leadRepository = $leadRepository;

        request()->request->add(['entity_type' => 'quotes']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin::quotes.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('admin::quotes.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Webkul\Attribute\Http\Requests\AttributeForm $request
     * @return \Illuminate\Http\Response
     */
    public function store(AttributeForm $request)
    {
        Event::dispatch('quote.create.before');

        $quote = $this->quoteRepository->create(request()->all());

        if (request('lead_id')) {
            $lead = $this->leadRepository->find(request('lead_id'));

            $lead->quotes()->attach($quote->id);
        }

        Event::dispatch('quote.create.after', $quote);
        
        session()->flash('success', trans('admin::app.quotes.create-success'));

        return redirect()->route('admin.quotes.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $quote = $this->quoteRepository->findOrFail($id);

        return view('admin::quotes.edit', compact('quote'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Webkul\Attribute\Http\Requests\AttributeForm $request
     * @param int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(AttributeForm $request, $id)
    {
        Event::dispatch('quote.update.before');

        $quote = $this->quoteRepository->update(request()->all(), $id);

        Event::dispatch('quote.update.after', $quote);
        
        session()->flash('success', trans('admin::app.quotes.update-success'));

        return redirect()->route('admin.quotes.index');
    }

    /**
     * Search quote results
     *
     * @return \Illuminate\Http\Response
     */
    public function search()
    {
        $results = $this->quoteRepository->findWhere([
            ['name', 'like', '%' . urldecode(request()->input('query')) . '%']
        ]);

        return response()->json($results);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->quoteRepository->findOrFail($id);
        
        try {
            Event::dispatch('settings.quotes.delete.before', $id);

            $this->quoteRepository->delete($id);

            Event::dispatch('settings.quotes.delete.after', $id);

            return response()->json([
                'status'    => true,
                'message'   => trans('admin::app.response.destroy-success', ['name' => trans('admin::app.quotes.quote')]),
            ], 200);
        } catch(\Exception $exception) {
            return response()->json([
                'status'    => false,
                'message'   => trans('admin::app.response.destroy-failed', ['name' => trans('admin::app.quotes.quote')]),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy()
    {
        $data = request()->all();

        $this->quoteRepository->destroy($data['rows']);

        return response()->json([
            'status'  => true,
            'message' => trans('admin::app.response.destroy-success', ['name' => trans('admin::app.quotes.title')]),
        ]);
    }

    /**
     * Print and download the for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $quote = $this->quoteRepository->findOrFail($id);

        return view('admin::quotes.pdf', compact('quote'));

        return PDF::loadHTML(view('admin::quotes.pdf', compact('quote'))->render())
            ->setPaper('a4')
            ->download('Quote_' . $quote->subject . '.pdf');
    }
}