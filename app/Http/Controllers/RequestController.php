<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Catalog;
use App\Domain\Sales\Privacy;
use App\Domain\Sales\Requests;
use App\Domain\Site\Site;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    private function common(): array
    {
        return ['idempotency_key' => 'required|uuid', 'email_opt_in' => 'nullable|boolean', 'website' => 'nullable|size:0'];
    }

    public function preorder(Request $request, Requests $service)
    {
        $limit = Catalog::quantityLimit();
        $data = $request->validate($this->common() + ['customer' => 'required|array:name,phone,email,company', 'customer.name' => 'required|string|min:2|max:120', 'customer.phone' => 'required|string|max:40|regex:/^[+0-9 ()-]{8,40}$/', 'customer.email' => 'nullable|email|max:254', 'customer.company' => 'nullable|string|max:180', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required|integer|min:1|max:'.$limit, 'notes' => 'nullable|string|max:3000']);
        if (strlen(Privacy::phone($data['customer']['phone'])) < 8 || strlen(Privacy::phone($data['customer']['phone'])) > 15) {
            abort(422, 'Revisa el teléfono.');
        }$result = $service->preorder($data, $request->session()->getId());

        return response()->json(['reference' => $result['code'], 'status' => 'registrada', 'notification' => 'pendiente'], $result['created'] ? 201 : 200);
    }

    public function contact(Request $request, Requests $service)
    {
        $data = $request->validate($this->common() + ['name' => 'required|string|min:2|max:120', 'phone' => 'required|string|max:40|regex:/^[+0-9 ()-]{8,40}$/', 'email' => 'nullable|email|max:254', 'company' => 'nullable|string|max:180', 'message' => 'required|string|min:5|max:5000']);
        if (strlen(Privacy::phone($data['phone'])) < 8 || strlen(Privacy::phone($data['phone'])) > 15) {
            abort(422, 'Revisa el teléfono.');
        }$result = $service->contact($data, $request->session()->getId());

        return response()->json(['reference' => $result['code'], 'status' => 'registrada', 'notification' => 'pendiente'], $result['created'] ? 201 : 200);
    }

    public function privacy()
    {
        return view('site.privacy', ['legal' => Privacy::legal(), 'page' => (object) ['title' => 'Política de privacidad', 'slug' => 'politica-de-privacidad', 'status' => Privacy::legal()['approved'] ? 'published' : 'draft'], 'site' => Site::context()]);
    }
}
