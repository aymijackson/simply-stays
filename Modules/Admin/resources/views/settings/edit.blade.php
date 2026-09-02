@extends('layouts.app')

@section('title', 'Site settings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('admin.index') }}">&larr; Back to admin overview</a>

        <div class="card" style="max-width: 720px; margin: 1rem auto 0;">
            <h1>Site settings</h1>
            <p>Manage the logo, footer contact details, social links, and legal pages shown across the site.</p>

            @if (session('status'))
                <x-alert type="success">{{ session('status') }}</x-alert>
            @endif

            <h3 style="margin-top: 1.5rem;">Logo</h3>
            @if ($settings->logoUrl())
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <img src="{{ $settings->logoUrl() }}" alt="Current logo" style="height: 48px; width: auto;">
                    <form method="POST" action="{{ route('admin.settings.logo.destroy') }}" onsubmit="return confirm('Remove the current logo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger-outline btn-sm">Remove logo</button>
                    </form>
                </div>
            @else
                <p class="form-hint" style="margin-bottom: 1rem;">No logo uploaded yet &mdash; the site name is shown as text instead.</p>
            @endif

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="form-group">
                    <label class="form-label" for="logo">Upload a new logo</label>
                    <input type="file" id="logo" name="logo" accept="image/*" class="form-control">
                    <p class="form-hint">PNG or SVG with a transparent background works best. Uploading a new file replaces the current logo.</p>
                    @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <hr>

                <h3>Contact details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="contact_email">Support email</label>
                        <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" class="form-control">
                        @error('contact_email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_phone">Support phone</label>
                        <input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings->contact_phone) }}" class="form-control">
                        @error('contact_phone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="contact_address">Address</label>
                    <input type="text" id="contact_address" name="contact_address" value="{{ old('contact_address', $settings->contact_address) }}" class="form-control">
                    @error('contact_address') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="footer_about">Footer about text</label>
                    <textarea id="footer_about" name="footer_about" rows="3" class="form-control">{{ old('footer_about', $settings->footer_about) }}</textarea>
                    @error('footer_about') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <hr>

                <h3>Social links</h3>
                <div class="form-group">
                    <label class="form-label" for="facebook_url">Facebook URL</label>
                    <input type="url" id="facebook_url" name="facebook_url" value="{{ old('facebook_url', $settings->facebook_url) }}" class="form-control" placeholder="https://facebook.com/yourpage">
                    @error('facebook_url') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="instagram_url">Instagram URL</label>
                    <input type="url" id="instagram_url" name="instagram_url" value="{{ old('instagram_url', $settings->instagram_url) }}" class="form-control" placeholder="https://instagram.com/yourpage">
                    @error('instagram_url') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="twitter_url">X / Twitter URL</label>
                    <input type="url" id="twitter_url" name="twitter_url" value="{{ old('twitter_url', $settings->twitter_url) }}" class="form-control" placeholder="https://x.com/yourpage">
                    @error('twitter_url') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <hr>

                <h3>Legal pages</h3>
                <p class="form-hint">Basic HTML (headings, paragraphs, lists, links) is allowed and will be rendered as-is on the public pages.</p>
                <div class="form-group">
                    <label class="form-label" for="terms_content">Terms &amp; Conditions</label>
                    <textarea id="terms_content" name="terms_content" rows="10" class="form-control">{{ old('terms_content', $settings->terms_content) }}</textarea>
                    @error('terms_content') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="privacy_content">Privacy Policy</label>
                    <textarea id="privacy_content" name="privacy_content" rows="10" class="form-control">{{ old('privacy_content', $settings->privacy_content) }}</textarea>
                    @error('privacy_content') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Save settings</button>
            </form>
        </div>
    </div>
</div>
@endsection
