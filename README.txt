# BailCompta 360 - Application Web de Gestion Comptable

Une application web conçue pour simplifier et automatiser les processus de gestion comptable, en palliant les limitations du logiciel ABS 2000 pour les besoins spécifiques de notre institution.

## Table des matières

1.  [Introduction](#1-introduction)
2.  [Fonctionnalités Principales](#2-fonctionnalités-principales)
    * [2.1. Saisie Intelligente des Écritures Comptables](#21-saisie-intelligente-des-écritures-comptables)
    * [2.2. Gestion Intégrée des Emprunts Bancaires](#22-gestion-intégrée-des-emprunts-bancaires)
    * [2.3. Gestion des Factures (Clients et Fournisseurs) pour Intégration Comptable](#23-gestion-des-factures-clients-et-fournisseurs-pour-intégration-comptable)
    * [2.4. Importation Optimisée des Relevés Bancaires (Format CSV)](#24-importation-optimisée-des-relevés-bancaires-format-csv)
    * [2.5. Génération de Fichiers Comptables Compatibles ABS 2000 (Format CSV)](#25-génération-de-fichiers-comptables-compatibles-abs-2000-format-csv)
    * [2.6. Gestion Fine des Habilitations (Profils et Utilisateurs)](#26-gestion-fine-des-habilitations-profils-et-utilisateurs)
3.  [Sécurité](#3-sécurité)
4.  [Informations Techniques](#4-informations-techniques)

## 1. Introduction

Ce projet vise à développer une application web de gestion comptable, "BailCompta 360", pour optimiser les tâches spécifiques non gérées efficacement par l'application bancaire ABS 2000. L'objectif principal est de réduire les opérations manuelles, de minimiser les risques d'erreurs et d'accélérer les temps de traitement des informations comptables.

## 2. Fonctionnalités Principales

### 2.1. Saisie Intelligente des Écritures Comptables

* **Fonctionnement :** Formulaire intuitif avec recherche dynamique de comptes, suggestion automatique de contreparties et ajout/suppression de lignes de débit/crédit via un tableau dynamique.
* **Rôle et Utilité :** Accélérer et simplifier la saisie, réduire les erreurs et automatiser l'identification des comptes.
* **Résultats Attendus :** Facilitation, réduction des opérations manuelles, gain de temps, limitation des erreurs et traçabilité accrue.

### 2.2. Gestion Intégrée des Emprunts Bancaires

* **Fonctionnement :** Formulaire détaillé pour la saisie des emprunts, génération automatique du plan d'amortissement visualisable et comptabilisation mensuelle automatisée.
* **Rôle et Utilité :** Centraliser la gestion des emprunts et de leurs amortissements, automatiser la génération des écritures comptables.
* **Résultats Attendus :** Base de données complète, consultation facilitée, génération automatique des écritures, préparation simplifiée des états financiers et visualisation claire des plans d'amortissement.

### 2.3. Gestion des Factures (Clients et Fournisseurs) pour Intégration Comptable

* **Fonctionnement :** Formulaires dédiés pour la saisie des factures clients et fournisseurs avec possibilité de déversement direct en comptabilité via un bouton.
* **Rôle et Utilité :** Centraliser la gestion des factures et automatiser leur intégration comptable.
* **Résultats Attendus :** Saisie structurée, génération automatique des écritures, réduction des erreurs et du temps de saisie, amélioration de la traçabilité.

### 2.4. Importation Optimisée des Relevés Bancaires (Format CSV)

* **Fonctionnement :** Formulaire de sélection de fichier CSV, affichage des données pour vérification et validation pour intégration dans le système.
* **Rôle et Utilité :** Accélérer l'intégration des transactions bancaires.
* **Résultats Attendus :** Importation rapide, visualisation pour contrôle et base pour l'automatisation future.

### 2.5. Génération de Fichiers Comptables Compatibles ABS 2000 (Format CSV)

* **Fonctionnement :** Sélection d'une période et génération d'un fichier CSV structuré pour ABS 2000 avec lien de téléchargement.
* **Rôle et Utilité :** Assurer la compatibilité avec le logiciel comptable existant.
* **Résultats Attendus :** Génération de fichiers au format standardisé et facilitation du transfert des données.

### 2.6. Gestion Fine des Habilitations (Profils et Utilisateurs)

* **Fonctionnement :** Interfaces pour définir des droits d'accès spécifiques par profil et utilisateur sur les fonctionnalités et les chapitres comptables.
* **Rôle et Utilité :** Garantir la sécurité et la confidentialité des données en contrôlant l'accès.
* **Résultats Attendus :** Définition précise des droits, sécurité renforcée et conformité aux exigences d'audit.

## 3. Sécurité

L'application BailCompta 360 intègre plusieurs mesures de sécurité pour protéger les données et les accès :

* **Authentification Sécurisée :**
    * Hachage des mots de passe avec `password_hash`.
    * Vérification des mots de passe avec `password_verify`.
    * Protection contre les attaques par force brute avec limitation des tentatives et blocage temporaire des comptes.
    * Gestion de session sécurisée via les paramètres `httponly`, `secure` (HTTPS recommandé) et `samesite`.
    * Fonctionnalité "Se souvenir de moi" avec tokens sécurisés et expiration.
    * (Optionnel) Vérification des horaires de connexion.
    * Filtrage des entrées utilisateur pour prévenir les injections.
    * Journalisation des erreurs d'authentification.
* **Gestion des Habilitations :** Contrôle d'accès précis aux fonctionnalités et aux données en fonction des profils et des utilisateurs.

## 4. Informations Techniques

* **Langage de Programmation :** PHP 8.2.12
* **Base de Données :** Microsoft SQL Server 2014
* **Environnement de Développement :** XAMPP 3.2.3
* **Frameworks et Bibliothèques :** Utilisation de bibliothèques JavaScript pour la gestion des fichiers CSV et des interfaces utilisateur.
* **Sécurité :** Implémentation des meilleures pratiques de sécurité pour la gestion des utilisateurs et des habilitations, incluant les mesures d'authentification détaillées dans la section [Sécurité](#3-sécurité).