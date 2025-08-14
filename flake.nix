{
  description = "A basic flake with a shell";
  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
  inputs.flake-utils.url = "github:numtide/flake-utils";

  outputs = { nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = nixpkgs.legacyPackages.${system};
        php = pkgs.php84.buildEnv {
          extensions = { all, ... }:
            with all; [
              bcmath
              curl
              dom
              fileinfo
              filter
              gd
              iconv
              intl
              mbstring
              openssl
              pdo
              pdo_mysql
              session
              simplexml
              sodium
              tokenizer
              xdebug
              xmlreader
              xmlwriter
              zip
              zlib
            ];
          extraConfig = ''
            xdebug.start_with_request = yes
            xdebug.discover_client_host = true
          '';
        };
      in {
        devShells.default = pkgs.mkShell {
          packages = with pkgs; [
            bashInteractive
            php
            php.packages.composer

            # ✅ 用 nodejs_22（內建 npm 10 / npx 正常）
            nodejs_22
          ];

          shellHook = ''
            mkdir -p .bin
            ln -sf ${php}/bin/php .bin/php
            ln -sf ${php.packages.composer}/bin/composer .bin/composer

            # 可選：開啟 corepack，之後可用 pnpm/yarn（如果需要）
            if command -v corepack >/dev/null 2>&1; then
              corepack enable >/dev/null 2>&1 || true
            fi

            echo "Node: $(node -v) | npm: $(npm -v)"
          '';
        };

        # nix run . artisan
        # nix run . vendor/bin/pint
        packages.default = php;

        # nix run .#composer install
        packages.composer = php.packages.composer;

        formatter = nixpkgs.legacyPackages.${system}.alejandra;
      }
    );
}
