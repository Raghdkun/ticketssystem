@extends("errors.layout")

@section("code", "429")
@section("title", app()->getLocale() === "ar" ? "محاولات كثيرة جدًا" : "Too many attempts")
@section("heading", app()->getLocale() === "ar" ? "محاولات كثيرة جدًا" : "Too many attempts")
@section("message", app()->getLocale() === "ar" ? "انتظر قليلًا ثم حاول مرة أخرى." : "Wait a moment, then try again.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
