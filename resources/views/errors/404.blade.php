@extends("errors.layout")

@section("code", "404")
@section("title", app()->getLocale() === "ar" ? "الصفحة غير موجودة" : "Page not found")
@section("heading", app()->getLocale() === "ar" ? "الصفحة غير موجودة" : "Page not found")
@section("message", app()->getLocale() === "ar" ? "الرابط الذي فتحته لم يعد موجودًا، أو ربما كتب بشكل خاطئ." : "The link you opened no longer exists, or it was mistyped.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
