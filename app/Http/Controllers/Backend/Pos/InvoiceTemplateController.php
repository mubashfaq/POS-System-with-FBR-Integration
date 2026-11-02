<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class InvoiceTemplateController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $templates = InvoiceTemplate::query()->orderBy('sort_order')->get();

            return DataTables::of($templates)->addIndexColumn()

                ->addColumn('image', function ($data) {
                    if ($data->image) {
                        return '<img src="' . asset($data->image) . '" alt="' . e($data->name) . '"height="60" width="80"
                        style="object-fit:contain;border:1px solid #eee;border-radius:6px;"
                        onerror="this.style.display=\'none\';">';
                    }

                    return '—';
                })

                ->addColumn('name', function ($data) {
                    $name = e($data->name);

                    if ($data->is_default) {
                        $name .= ' <span class="badge bg-success ml-1">Default</span>';
                    }

                    return $name;
                })

                ->addColumn('key', function ($data) {
                    return '<span class="badge bg-light border">' . e($data->key) . '</span>';
                })

                ->addColumn('view', function ($data) {
                    return '<small>' . e($data->view) . '</small>';
                })

                ->addColumn('is_active', function ($data) {
                    return $data->is_active ? '<span class="badge bg-primary">Active</span>'
                        : '<span class="badge badge-secondary">Inactive</span>';
                })

                ->addColumn('action', function ($data) {
                    $editUrl = route('backend.admin.invoice-templates.edit', $data->id);
                    $deleteUrl = route('backend.admin.invoice-templates.destroy', $data->id);

                    return '
                    <div class="btn-group">
                        <button type="button" class="btn btn-flat">
                            Action
                        </button>

                        <button type="button"
                            class="btn btn-flat dropdown-toggle dropdown-icon"
                            data-toggle="dropdown"
                            aria-expanded="false">
                            <span class="sr-only">Toggle Dropdown</span>
                        </button>

                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="' . $editUrl . '">
                                <i class="fas fa-edit"></i> Edit
                            </a>

                            <div class="dropdown-divider"></div>

                            <form action="' . $deleteUrl . '"
                                method="POST"
                                onsubmit="return confirm(\'Delete this template?\')">
                                ' . csrf_field() . '
                                ' . method_field('DELETE') . '

                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>';
                })

                ->rawColumns([
                    'image',
                    'name',
                    'key',
                    'view',
                    'is_active',
                    'action',
                ])
                ->toJson();
        }

        return view('backend.invoice-templates.index');
    }

    public function create()
    {
        return view('backend.invoice-templates.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['key'] = Str::slug($data['key']);

        $uploadedPath = null;

        try {
            if ($request->hasFile('image_file')) {
                $uploadedPath = $this->uploadImage($request->file('image_file'));
                $data['image'] = $uploadedPath;
            }
            unset($data['image_file']);

            InvoiceTemplate::create($data);
        } catch (\Throwable $e) {
            // cleanup the uploaded file if DB save failed
            if ($uploadedPath) {
                $this->deleteImageFile($uploadedPath);
            }
            throw $e;
        }

        return redirect()->route('backend.admin.invoice-templates.index')->with('success', 'Template created.');
    }

    public function edit($id)
    {
        $template = InvoiceTemplate::findOrFail($id);
        return view('backend.invoice-templates.edit', compact('template'));
    }

    public function update(Request $request, $id)
    {
        $template = InvoiceTemplate::findOrFail($id);

        $data = $this->validateData($request, $template->id);
        $data['key'] = Str::slug($data['key']);

        $oldImage = $template->image;
        $newImage = null;

        if ($request->hasFile('image_file')) {
            $newImage = $this->uploadImage($request->file('image_file'));
            $data['image'] = $newImage;
        } else {
            unset($data['image']);
        }
        unset($data['image_file']);

        try {
            $template->update($data);
        } catch (\Throwable $e) {
            // save failed → clean up newly uploaded file
            if ($newImage) {
                $this->deleteImageFile($newImage);
            }
            throw $e;
        }

        // DB success → remove old file
        if ($newImage && $oldImage && $oldImage !== $newImage) {
            $this->deleteImageFile($oldImage);
        }

        return redirect()->route('backend.admin.invoice-templates.index')->with('success', 'Template updated.');
    }

    public function destroy($id)
    {
        $template = InvoiceTemplate::findOrFail($id);

        if ($template->is_default) {
            return back()->with('error', 'Cannot delete the default template.');
        }

        $inUse = InvoiceSetting::where('template', $template->key)->exists();
        if ($inUse) {
            return back()->with('error', 'This template is currently selected by a business. Switch them first.');
        }

        // 🔑 delete image file before row
        $this->deleteImageFile($template->image);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function validateData(Request $request, $ignoreId = null): array
    {
        $data = $request->validate([
            'key' => 'required|string|max:60|unique:invoice_templates,key' . ($ignoreId ? ",$ignoreId" : ''),
            'name' => 'required|string|max:100',
            'view' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z0-9._\-]+$/'],
            'image' => 'nullable|string|max:255',
            'image_file' => 'nullable|image|max:2048',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|numeric|min:0',
        ], [
            'view.regex' => 'View path may only contain letters, numbers, dots, dashes, and underscores.',
        ]);

        // 🔑 Check the Blade file physically exists
        $viewPath = str_replace('.', '/', $data['view']); // backend.orders.x → backend/orders/x
        if (!file_exists(resource_path('views/' . $viewPath . '.blade.php'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'view' => "Blade file not found at resources/views/{$viewPath}.blade.php. Create the file first, then add this template.",
            ]);
        }


        return $data + [
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $request->input('sort_order', 0),
        ];
    }

    private function uploadImage($file): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time()
            . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('assets/images/invoice-templates'), $name);
        return 'assets/images/invoice-templates/' . $name;
    }

    private function deleteImageFile(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        // Only allow deletion inside our known directory (safety)
        $allowedPrefix = 'assets/images/invoice-templates/';
        if (!str_starts_with($path, $allowedPrefix)) {
            return;
        }

        $fullPath = public_path($path);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}