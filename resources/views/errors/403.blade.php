@extends("errors.layout")

@section("code", "403")
@section("title", app()->getLocale() === "ar" ? "لا تملك صلاحية الوصول" : "You do not have access")
@section("heading", app()->getLocale() === "ar" ? "لا تملك صلاحية الوصول" : "You do not have access")
@section("message", app()->getLocale() === "ar" ? "هذه الصفحة تخص حسابًا آخر." : "This page belongs to a different account.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
