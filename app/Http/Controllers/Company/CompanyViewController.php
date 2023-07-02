<?php

namespace App\Http\Controllers\Company;

use App\Constants\Ability;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Country\CountryModelController;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Address;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyViewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $companies = Company::query()
            ->when($request->get('search'), static function (Builder $query, string $search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('abbreviation', 'like', "%$search%");
            })
            ->paginate($request->get('per_page', 10))
            ->through(fn($company) => [ // todo user resource
                'id' => $company->id,
                'name' => $company->name,
                'abbreviation' => $company->abbreviation,
                'logo' => $company->logo,
                'created_at' => $company->created_at,
                'can' => [
                    Ability::DELETE => $user->can(Ability::DELETE, $company)
                ]
            ])
            ->withQueryString()
            ->onEachSide(0);

        return Inertia::render('Company/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
            'can' => [
                Ability::CREATE => $user->can(Ability::CREATE, Company::class),
                Ability::DELETE_ANY => $user->can(Ability::DELETE_ANY, Company::class)
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function details(Company $company): Response
    {
        if ($company->logo) {
            $company->logo = Storage::url($company->logo);
        }

        $address = $company->address;

        return Inertia::render('Company/Details', [
            'company' => $company,
            'address' => $address,
            'country' => $address?->country,
            'countries' => CountryModelController::getAll(),
            'company_users' => $company->users,
            'non_company_users' => CompanyModelController::nonCompanyUser($company)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @throws AuthorizationException
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->authorize(Ability::CREATE, Company::class);

        if ($path = $this->uploadLogo($request)) {
            $validated = $request->validated();
            $validated['logo'] = $path;

            $error = '';

            if (!is_null($company = Company::create($validated))) {
                if (is_null($this->updateOrCreateAddress($request, $company))) {
                    $error = 'fail to create address for the company';
                }
            } else {
                $error = 'fail to create company';
            }

            if ($error) {
                return back('400')->withErrors('message', $error);
            }

            return Redirect::route('companies.details', [
                'company' => $company->id
            ])->with('message', 'company created');
        }

        return back(409, [
            'company' => null
        ])->withErrors('fail to upload logo', 'logo');
    }

    private function uploadLogo(StoreCompanyRequest|UpdateCompanyRequest $request): ?string
    {
        $logo = $request->file('logo');

        return $logo->isValid()
            ? Storage::putFile('public/company', $logo, 'public')
            : null;
    }

    /**
     * Show the form for creating a new resource.
     * @throws AuthorizationException
     */
    public function create(): Response
    {
        $this->authorize(Ability::CREATE, Company::class);

        return Inertia::render('Company/Details', [
            'company' => null,
            'address' => null,
            'country' => null,
            'countries' => CountryModelController::getAll(),
            'company_users' => null,
            'non_company_users' => null,
        ]);
    }

    private function updateOrCreateAddress(StoreCompanyRequest|UpdateCompanyRequest $request, Company $company): ?Address
    {
        if (!is_null($address = $company->address)) {
            $address->update([
                'line_1' => $request->get('address_line_1'),
                'line_2' => $request->get('address_line_2'),
                'zip_code' => $request->get('address_zip_code'),
                'city' => $request->get('address_city'),
                'state_or_region' => $request->get('address_state_or_region'),
                'country_id' => $request->get('address_country'),
            ]);
            return $address;
        }

        return Address::create([
            'line_1' => $request->get('address_line_1'),
            'line_2' => $request->get('address_line_2'),
            'zip_code' => $request->get('address_zip_code'),
            'city' => $request->get('address_city'),
            'state_or_region' => $request->get('address_state_or_region'),
            'country_id' => $request->get('address_country'),
            'addressable_id' => $company->id,
            'addressable_type' => get_class($company),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     */
    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize(Ability::UPDATE, $company);

        if (is_null($path = $this->uploadLogo($request))) {
            return back(409, [
                'company' => null
            ])->withErrors('logo file path is null', 'logo');
        }

        $validated = $request->validated();
        $validated['logo'] = $path;

        $error = '';

        if (!is_null($company->update($validated))) {
            if (!$this->updateOrCreateAddress($request, $company)) {
                $error = 'fail to create address for the company';
            }
        } else {
            $error = 'fail to create company';
        }

        if ($error) {
            return back('400')->withErrors('message', $error);
        }

        return Redirect::route('companies.details', [
            'company' => $company->id
        ])->with('message', 'company-created');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return Redirect::route('companies.index')
            ->with('message', "$company->name is deleted");
    }
}
