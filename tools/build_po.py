#!/usr/bin/env python3
"""Build locales/<lang>.po from locales/erm.pot and the translation tables below, then compile .mo files.

Requires Babel (pip install babel). Usage: python3 tools/build_po.py
"""
import os
import re
import sys

from babel.messages import pofile, mofile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from translations_es import ES  # noqa: E402

ES_ES = [("tenés", "tienes"), ("Elegí", "Elige"), ("sos", "eres"), ("Dejalo", "Déjalo"), ("Ajustá", "Ajusta"), ("No sos dueño", "No eres dueño"), ("No tenés", "No tienes"), ("Dejalos", "Déjalos"), ("Revisá", "Revisa"), ("documentá", "documenta"), ("Definí", "Define"), ("Vinculá", "Vincula"), ("Sos dueño", "Eres dueño"), ("asignada a vos", "asignada a ti"), ("Describí", "Describe"), ("revisá", "revisa"), ("Definí", "Define"), ("actualizala", "actualízala"), ("de tu riesgo", "de tu riesgo"), ("actualizá", "actualiza"), ("revisá el plan", "revisa el plan"), ("Actualizá", "Actualiza"), ("registrá", "registra"), ("No tenés acceso", "No tienes acceso"), ("Revisá la evaluación corporativa", "Revisa la evaluación corporativa"), ("elegí otra", "elige otra"), ("Elegí una búsqueda", "Elige una búsqueda"), ("Verificá", "Verifica"), ("Revisá las sugerencias", "Revisa las sugerencias"), ("Describí qué podría", "Describe qué podría"), ("por vos", "por ti"), ("Elegí uno, adaptalo a tu situación en el asistente y guardalo", "Elige uno, adáptalo a tu situación en el asistente y guárdalo"), ("Respondé con tus palabras", "Responde con tus palabras"), ("podés completar", "puedes completar"), ("Adaptala", "Adáptala"), ("Describí el evento", "Describe el evento"), ("Decí qué podría", "Di qué podría"), ("pensá en el peor", "piensa en el peor"), ("imaginá que", "imagina que"), ("no estás seguro, elegí", "no estás seguro, elige"), ("Fijá", "Fija"), ("registrá la revisión", "registra la revisión"), ("Describí el riesgo", "Describe el riesgo"), ("Agregá", "Añade"), ("agregá", "añade"), ("Asigná", "Asigna"), ("Evaluá", "Evalúa"), ("Revisá ambas", "Revisa ambas"), ("explicá", "explica"), ("decidí si", "decide si"), ("Tomá la peor", "Toma la peor"), ("hacé clic", "haz clic"), ("actualizá el riesgo", "actualiza el riesgo"), ("Puntuá", "Puntúa"), ("describí las", "describe las"), ("Compará", "Compara"), ("abrilos", "ábrelos"), ("Registrá los eventos", "Registra los eventos"), ("Elegí indicadores", "Elige indicadores"), ("Vinculá el riesgo", "Vincula el riesgo"), ("Anotá", "Anota"), ("describí qué", "describe qué"), ("querés alcanzar", "quieres alcanzar"), ("no sabés", "no sabes"), ("elegí tu", "elige tu"), ("Escribilo", "Escríbelo"), ("elegí Media", "elige Media"), ("Chance de que pase", "Probabilidad de que ocurra"), ("tu empresa", "tu empresa"), ("Si ya pasó", "Si ya ocurrió"), ("creá una nueva", "crea una nueva"), ("Puntuá", "Puntúa"), ("Describí brevemente lo que querés", "Describe brevemente lo que quieres"), ("Explicale", "Explícale"), ("puntuá el impacto", "puntúa el impacto"), ("y explicá", "y explica"), ("Sos el autor", "Eres el autor"), ("¿Qué querés", "¿Qué quieres"), ("No enviaste", "No has enviado"), ("Escribiste o editaste", "Has escrito o editado"), ("No podés mover", "No puedes mover"), ("no tenés acceso", "no tienes acceso"), ("No tenés permiso para actualizar", "No tienes permiso para actualizar")]


def build(lang: str, table: dict, replace: list) -> None:
    with open(os.path.join(ROOT, "locales", "erm.pot"), "rb") as fh:
        catalog = pofile.read_po(fh)
    catalog.locale = lang
    catalog.language_team = lang
    missing = []
    for msg in catalog:
        if not msg.id:
            continue
        key = msg.id if isinstance(msg.id, str) else tuple(msg.id)
        value = table.get(key)
        if value is None:
            missing.append(key)
            continue
        if isinstance(value, tuple):
            value = tuple(_apply(v, replace) for v in value)
        else:
            value = _apply(value, replace)
        msg.string = value
    path = os.path.join(ROOT, "locales", f"{lang}.po")
    with open(path, "wb") as fh:
        pofile.write_po(fh, catalog, width=120)
    with open(os.path.join(ROOT, "locales", f"{lang}.mo"), "wb") as fh:
        mofile.write_mo(fh, catalog)
    print(f"{lang}: {len(catalog) - len(missing)} translated, {len(missing)} missing")
    for m in missing:
        print("   MISSING:", m)


def _apply(text: str, replace: list) -> str:
    for a, b in replace:
        text = re.sub(r"\b" + re.escape(a) + r"\b", b, text)
    return text


if __name__ == "__main__":
    build("es_AR", ES, [])
    build("es_ES", ES, ES_ES)
