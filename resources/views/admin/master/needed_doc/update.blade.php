@extends('admin.layouts.app')
@section('title', 'Main page')

@section('content')
{{-- {{session()->get('errors')}} --}}

    <!-- Start Page content -->
    <div class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-sm-12">
                    <div class="box">

                        <div class="box-header with-border">
                            <a href="{{ url('needed_doc') }}">
                                <button class="btn btn-danger btn-sm pull-right" type="submit">
                                    <i class="mdi mdi-keyboard-backspace mr-2"></i>
                                    @lang('view_pages.back')
                                </button>
                            </a>
                        </div>

                        <div class="col-sm-12">

                            <form method="post" class="form-horizontal" action="{{ url('needed_doc/update',$item->id) }}">
                                @csrf

                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="name">@lang('view_pages.name') <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" id="name" name="name"
                                                value="{{ old('name',$item->name) }}" required=""
                                                placeholder="@lang('view_pages.enter') @lang('view_pages.name')">
                                            <span class="text-danger">{{ $errors->first('name') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="">@lang('view_pages.account_type') <span class="text-danger">*</span></label>
                                            <select name="account_type" id="account_type" class="form-control" required>
                                                <option value="" selected disabled>@lang('view_pages.select')</option>
                                                <option value="individual" {{ old('account_type',$item->account_type) == 'individual' ? 'selected' : '' }}>@lang('view_pages.individual')</option>

                                                <option value="fleet_driver" {{ old('account_type',$item->account_type) == 'fleet_driver' ? 'selected' : '' }}>@lang('view_pages.fleet_drivers')</option>
                                               
                                                <option value="both" {{ old('account_type',$item->account_type) == 'both' ? 'selected' : '' }}>@lang('view_pages.both')</option>
                                            </select>
                                            <span class="text-danger">{{ $errors->first('account_type') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="">@lang('view_pages.has_expiry_date') <span class="text-danger">*</span></label>
                                            <select name="has_expiry_date" id="has_expiry_date" class="form-control" required>
                                                <option value="" selected disabled>@lang('view_pages.select')</option>
                                                <option value="1" {{ old('has_expiry_date',$item->has_expiry_date) == '1' ? 'selected' : '' }}>@lang('view_pages.yes')</option>
                                                <option value="0" {{ old('has_expiry_date',$item->has_expiry_date) == '0' ? 'selected' : '' }}>@lang('view_pages.no')</option>
                                            </select>
                                            <span class="text-danger">{{ $errors->first('has_expiry_date') }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- <div class="col-6">
                                        <div class="form-group">
                                            <label for="">@lang('view_pages.doc_type') <span class="text-danger">*</span></label>
                                            <select name="doc_type" id="doc_type" class="form-control" required>
                                                <option value="" selected disabled>@lang('view_pages.select')</option>
                                                @foreach ($format as $format)
                                                    <option value="{{ $format }}" {{ old('doc_type',$item->doc_type) == $format ? 'selected' : '' }}>{{ ucfirst($format) }}</option>
                                                @endforeach
                                            </select>
                                            <span class="text-danger">{{ $errors->first('doc_type') }}</span>
                                        </div>
                                    </div> -->

                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="">@lang('view_pages.has_identify_number') <span class="text-danger">*</span></label>
                                            <select name="has_identify_number" id="has_identify_number" class="form-control" required>
                                                <option value="" selected disabled>@lang('view_pages.select')</option>
                                                <option value="1" {{ old('has_identify_number',$item->has_identify_number) == '1' ? 'selected' : '' }}>@lang('view_pages.yes')</option>
                                                <option value="0" {{ old('has_identify_number',$item->has_identify_number) == '0' ? 'selected' : '' }}>@lang('view_pages.no')</option>
                                            </select>
                                            <span class="text-danger">{{ $errors->first('has_identify_number') }}</span>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 d-none" id="identify_number_div">
                                        <div class="form-group">
                                            <label for="identify_number_locale_key">@lang('view_pages.identify_number_locale_key') <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" id="identify_number_locale_key" name="identify_number_locale_key"
                                                value="{{ old('identify_number_locale_key',$item->identify_number_locale_key) }}" 
                                                placeholder="@lang('view_pages.enter') @lang('view_pages.identify_number_locale_key')">
                                            <span class="text-danger">{{ $errors->first('identify_number_locale_key') }}</span>
                                        </div>
                                    </div>
                                </div>


                                <div class="form-group">
                                    <div class="col-12">
                                        <button class="btn btn-primary btn-sm pull-right m-5" type="submit">
                                            @lang('view_pages.update')
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- container -->
</div>
    <!-- content -->

<script>
    let hasIdentifyNumber = "{{ old('has_identify_number',$item->has_identify_number) }}";
    toggleHasIdentifyNumber(hasIdentifyNumber);

    function toggleHasIdentifyNumber(hasIdentifyNumber){

        if(hasIdentifyNumber == 1){
            $('#identify_number_div').removeClass('d-none');
            $('#identify_number_locale_key').attr('required',true);
        }else{
            $('#identify_number_div').addClass('d-none');
            $('#identify_number_locale_key').attr('required',false);
        }
    }

    $('#has_identify_number').change(function(){
        let val = $(this).val();
        toggleHasIdentifyNumber(val);
    });
</script>
@endsection