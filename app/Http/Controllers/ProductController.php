<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;
use function MongoDB\BSON\fromPHP;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        $title = $request->title ?? null;
        $date = $request->date ?? null;
        $price_from = $request->price_from ?? null;
        $variant = $request->variant ?? null;
        $price_to = $request->price_to ?? null;

        $products = Product::query()
            ->with(['product_variant_prices' => function ($query) use ($variant, $price_from, $price_to) {
                $query
                    ->when($variant, function ($query) use ($variant) {
                        return $query->where('variant', 'like', '%' . $variant . '%');
                    })
                    ->when($price_from, function ($query) use ($price_from) {
                        return $query->where('price', '>=', $price_from);
                    })
                    ->when($price_to, function ($query) use ($price_to) {
                        return $query->where('price', '<=', $price_to);
                    });
            }])
            ->when($title, function ($query) use ($title) {
                return $query->where('title', 'like', '%' . $title . '%');
            })
            ->when($date, function ($query) use ($date) {
                return $query->whereDate('created_at', $date);
            })
            ->paginate(10);

        $product_variants = ProductVariant::all();
        $options = [];
        foreach ($product_variants as $variant) {
            $option = json_decode($variant->variant);
            $name = Variant::find($variant->variant_id)->title ?? 'Other';
            foreach ($option as $o) {
                if (!array_key_exists($name, $options)) {
                    $options[$name] = [];
                }
                if (!in_array($o, $options[$name])) {
                    array_push($options[$name], $o);
                }
            }

        }

//        dd($options);

        return view('products.index', compact('products', 'title', 'price_from', 'price_to', 'date', 'options', 'variant'));
    }

    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }

    public function store(Request $request)
    {
//        dd($request->all());
        $fields = $request->validate(['title' => 'required', 'sku' => 'required', 'description' => 'nullable']);

        $product = Product::create($fields);

        foreach ($request->product_image ?? [] as $image) {
            $filePath = $this->savePhoto($image['dataURL'], $image['upload']['filename'] ?? 'test.png');
            ProductImage::create(['product_id' => $product->id, 'file_path' => $filePath]);
        }

        foreach ($request->product_variant ?? [] as $variant) {
            $variant_id = $variant['option'];
            $tags = $variant['tags'];
            ProductVariant::create(['product_id' => $product->id, 'variant_id' => $variant_id, 'variant' => json_encode($tags)]);
        }
        foreach ($request->product_variant_prices ?? [] as $product_variant_price) {
            $title = $product_variant_price['title'];
            $price = $product_variant_price['price'];
            $stock = $product_variant_price['stock'];
            ProductVariantPrice::create(['product_id' => $product->id, 'stock' => $stock, 'price' => $price, 'variant' => $title]);
        }


        return ['message' => 'Successfully Added The Product'];

    }

    private function savePhoto($photo, $name)
    {
        $img = $photo;
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $file = "images/" . $name;
        $success = file_put_contents($file, $data);
        return $success ? $file : 'Unable to save the file.';
//        return $file;
    }


    public function show($product)
    {

    }


    public function edit(Product $product)
    {
        $variants = Variant::all();

        $options = $product->product_variants->map(function ($item) {
            $record = [];
            $record['tags'] = json_decode($item->variant);
            $record['variant_id'] = $item->variant_id;
            return $record;
        });
        $product_variant_price = $product->product_variant_prices;

        return view('products.edit', compact('variants', 'product', 'options', 'product_variant_price'));
    }


    public function update(Request $request, Product $product)
    {

        $fields = $request->validate(['title' => 'required', 'sku' => 'required', 'description' => 'nullable']);
        $product->update($fields);

        // Delete Stuff

        ProductImage::query()->where('product_id', $product->id)->delete();
        ProductVariant::query()->where('product_id', $product->id)->delete();
        ProductVariantPrice::query()->where('product_id', $product->id)->delete();

        foreach ($request->product_image ?? [] as $image) {
            $filePath = $this->savePhoto($image['dataURL'], $image['name']);
            ProductImage::create(['product_id' => $product->id, 'file_path' => $filePath]);
        }

        foreach ($request->product_variant ?? [] as $variant) {
            $variant_id = $variant['option'];
            $tags = $variant['tags'];
            ProductVariant::create(['product_id' => $product->id, 'variant_id' => $variant_id, 'variant' => json_encode($tags)]);
        }
        foreach ($request->product_variant_prices ?? [] as $product_variant_price) {
            $title = $product_variant_price['title'];
            $price = $product_variant_price['price'];
            $stock = $product_variant_price['stock'];
            ProductVariantPrice::create(['product_id' => $product->id, 'stock' => $stock, 'price' => $price, 'variant' => $title]);
        }


    }


    public function destroy(Product $product)
    {

    }
}
