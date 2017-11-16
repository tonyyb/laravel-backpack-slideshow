<?php

namespace Novius\Backpack\Slideshow\Http\Controllers\Admin;

use Illuminate\Support\Facades\Request;
use Novius\Backpack\CRUD\Http\Controllers\CrudController;
use Novius\Backpack\Slideshow\Http\Requests\Admin\SlideRequest as StoreRequest;
use Novius\Backpack\Slideshow\Http\Requests\Admin\SlideRequest as UpdateRequest;
use Novius\Backpack\Slideshow\Models\Slide;
use Novius\Backpack\Slideshow\Models\Slideshow;

class SlideCrudController extends CrudController
{
    public function setup()
    {
        $this->crud->setModel(Slide::class);
        $this->crud->setRoute(config('backpack.base.route_prefix').'/slide');
        $this->crud->setIndexRoute('crud.slide.index', ['id' => (int) request('id')]);
        $this->crud->setReorderRoute('crud.slide.reorder', ['id' => (int) request('id')]);
        $this->crud->setEntityNameStrings(trans('backpack_slideshow::slideshow.slide'), trans('backpack_slideshow::slideshow.slides'));

        $this->crud->addColumn([
            'name' => 'title',
            'label' => trans('backpack_slideshow::slideshow.title'),
        ]);

        $this->crud->addColumn([
            // run a function on the CRUD model and show its return value
            'name' => 'image',
            'label' => trans('backpack_slideshow::slideshow.image'), // Table column heading
            'type' => 'model_function',
            'function_name' => 'thumbnailAdmin', // the method in your Model
        ]);

        $this->crud->addfield([
            'name' => 'slideshow_id',
            'type' => 'hidden',
            'default' => (int) request('id'),
        ]);

        $this->crud->addfield([
            'name' => 'title',
            'label' => trans('backpack_slideshow::slideshow.title'),
        ]);

        $this->crud->addfield([
            'name' => 'subtitle',
            'label' => trans('backpack_slideshow::slideshow.subtitle'),
        ]);

        $this->crud->addfield([
            'name' => 'text',
            'label' => trans('backpack_slideshow::slideshow.text'),
            'type' => 'ckeditor',
        ]);

        $this->crud->addfield([
            'name' => 'link',
            'label' => trans('backpack_slideshow::slideshow.link'),
            'type' => 'url',
        ]);

        $this->crud->orderBy('lft');

        $this->configureReorder();
    }

    protected function configureReorder()
    {
        $this->crud->allowAccess('reorder');
        $this->crud->enableReorder('title', 1);

        $this->crud->setReorderRoute('crud.slide.index', ['id' => Request::get('id')]);

        // The correct way if the PR is accepted https://github.com/Laravel-Backpack/CRUD/pull/932
        // $this->setReorderFilterCallback(function(){});

        // Alternate way avoiding extension of CrudController in Novius Backpack extended
        // (overriding the view Reorder)
        $this->data['reorder_filter_callback'] = function ($value, $key) {
            $isValid = true;
            $slideshowId = (int) request('id');
            if ($slideshowId) {
                $isValid = $value->slideshow_id == $slideshowId;
            }

            return $isValid;
        };
    }

    /**
     * List the slides of selected slideshow
     *
     * @return \Backpack\CRUD\app\Http\Controllers\Response
     */
    public function index()
    {
        $idSlideshow = \Request::get('id');
        Slideshow::findOrFail($idSlideshow);

        $this->crud->addClause('where', 'slideshow_id', $idSlideshow);

        $this->crud->removeButton('create'); // Recreate bellow with slideshow GET parameter
        $this->crud->addButtonFromView('top', 'add_slide', 'slideshow-create-slide', 'beginning');

        return parent::index();
    }

    /**
     * Show the form for creating inserting a new row.
     *
     * @return Response
     */
    public function create()
    {
        $idSlideshow = \Request::get('id');
        $slideshow = Slideshow::findOrFail($idSlideshow);

        $this->addImageField($slideshow);

        return parent::create();
    }

    protected function addImageField(Slideshow $slideshow)
    {
        $this->crud->addfield([
            'name' => 'image',
            'label' => implode('. ', [trans('backpack_slideshow::slideshow.image'),trans('backpack_slideshow::slideshow.recommended_size')]).' '.$slideshow->formatLabel(),
            'type' => 'image',
            'upload' => true,
            'crop' => true, // set to true to allow cropping, false to disable
            'aspect_ratio' => $slideshow->ratio(), // ommit or set to 0 to allow any aspect ratio
            'prefix' => 'storage/', // in case you only store the filename in the database, this text will be prepended to the database value
        ])->beforeField('title');
    }

    public function edit($id)
    {
        $slide = $this->crud->getEntry($id);
        $this->crud->setIndexRoute('crud.slide.index', ['id' => $slide->slideshow_id]);
        $slideshow = $slide->slideshow;
        $this->addImageField($slideshow);

        return parent::edit($id);
    }

    /**
     * Overrides save action. Removes actions save_and_back and save_and_new
     * because there is no way to override the index route in the action button.
     *
     * @return array
     */
    public function getSaveAction()
    {
        $this->setSaveAction('save_and_edit');
        $saveAction = parent::getSaveAction();        
        unset($saveAction['options']['save_and_back']);
        unset($saveAction['options']['save_and_new']);

        return $saveAction;
    }

    /**
     * @param StoreRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreRequest $request)
    {
        return parent::storeCrud($request);
    }

    /**
     * @param UpdateRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateRequest $request)
    {
        return parent::updateCrud($request);
    }
}
