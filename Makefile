# Lunch Bot Makefile - ngrok integration
.PHONY: all help ngrok ngrok-setup ngrok-status ngrok-stop ngrok-logs ngrok-background ngrok-url

# Default shell for better error handling
SHELL := /bin/bash

# Laravel default port
APP_PORT := 4480

# Colors for output
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
BLUE := \033[0;34m
PURPLE := \033[0;35m
CYAN := \033[0;36m
NC := \033[0m # No Color

## Default target
all: help

# ============================================================================
# NGROK COMMANDS
# ============================================================================

## Start ngrok tunnel for local development
ngrok:
	@echo -e "$(BLUE)Starting ngrok tunnel...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(CYAN)Starting tunnel on port $(APP_PORT)...$(NC)"
	@echo -e "$(YELLOW)============================================================$(NC)"
	@echo -e "$(GREEN)The tunnel URL will appear below once connected:$(NC)"
	@echo -e "$(YELLOW)Press Ctrl+C to stop the tunnel$(NC)"
	@echo -e "$(YELLOW)============================================================$(NC)"
	@ngrok http $(APP_PORT)

## Start ngrok in background and display URL
ngrok-background:
	@echo -e "$(BLUE)Starting ngrok tunnel in background...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@if pgrep -x "ngrok" > /dev/null; then \
		echo -e "$(YELLOW)Stopping existing ngrok process...$(NC)"; \
		killall ngrok 2>/dev/null || true; \
		sleep 2; \
	fi
	@echo -e "$(CYAN)Starting tunnel on port $(APP_PORT) in background...$(NC)"
	@nohup ngrok http $(APP_PORT) > /dev/null 2>&1 &
	@echo -e "$(YELLOW)Waiting for tunnel to establish...$(NC)"
	@sleep 3
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		echo -e "$(GREEN)Tunnel established successfully!$(NC)"; \
		echo -e "$(YELLOW)============================================================$(NC)"; \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)Your public URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)============================================================$(NC)"; \
		echo -e "$(BLUE)Web interface: http://localhost:4040$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-url' to get the URL again$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-stop' to stop the tunnel$(NC)"; \
	else \
		echo -e "$(RED)Failed to establish tunnel$(NC)"; \
		echo -e "$(YELLOW)Check if port $(APP_PORT) is accessible$(NC)"; \
	fi

## Get current ngrok URL
ngrok-url:
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)Current ngrok URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)============================================================$(NC)"; \
		echo -e "$(BLUE)Web interface: http://localhost:4040$(NC)"; \
	else \
		echo -e "$(RED)No active ngrok tunnel found$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-background' to start a tunnel$(NC)"; \
	fi

## Install and configure ngrok
ngrok-setup:
	@echo -e "$(BLUE)Setting up ngrok...$(NC)"
	@if command -v ngrok &> /dev/null; then \
		echo -e "$(YELLOW)ngrok is already installed$(NC)"; \
		ngrok version; \
	else \
		echo -e "$(CYAN)Installing ngrok...$(NC)"; \
		if [[ "$$(uname)" == "Darwin" ]]; then \
			if command -v brew &> /dev/null; then \
				brew install ngrok/ngrok/ngrok; \
			else \
				echo -e "$(YELLOW)Installing via download...$(NC)"; \
				curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.zip -o ngrok.zip; \
				unzip -o ngrok.zip; \
				sudo mv ngrok /usr/local/bin/; \
				rm ngrok.zip; \
			fi; \
		elif [[ "$$(uname)" == "Linux" ]]; then \
			curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null; \
			echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list; \
			sudo apt update && sudo apt install ngrok; \
		else \
			echo -e "$(RED)Unsupported OS. Please install ngrok manually from https://ngrok.com$(NC)"; \
			exit 1; \
		fi; \
		echo -e "$(GREEN)ngrok installed successfully!$(NC)"; \
	fi
	@echo -e ""
	@echo -e "$(CYAN)Authentication:$(NC)"
	@if ngrok config check 2>/dev/null | grep -q "Valid"; then \
		echo -e "$(GREEN)ngrok is already authenticated$(NC)"; \
	else \
		echo -e "$(YELLOW)You need to authenticate ngrok$(NC)"; \
		echo -e "$(CYAN)1. Create a free account at: https://dashboard.ngrok.com/signup$(NC)"; \
		echo -e "$(CYAN)2. Get your authtoken from: https://dashboard.ngrok.com/get-started/your-authtoken$(NC)"; \
		echo -e "$(CYAN)3. Run: ngrok config add-authtoken YOUR_TOKEN$(NC)"; \
	fi
	@echo -e ""
	@echo -e "$(GREEN)Quick start:$(NC)"
	@echo -e "  $(BLUE)make ngrok$(NC)            - Start tunnel on port $(APP_PORT)"
	@echo -e "  $(BLUE)make ngrok-background$(NC) - Start tunnel in background"
	@echo -e "  $(BLUE)make ngrok-status$(NC)     - Check tunnel status"
	@echo -e "  $(BLUE)make ngrok-stop$(NC)       - Stop all tunnels"

## Check ngrok status and active tunnels
ngrok-status:
	@echo -e "$(BLUE)Checking ngrok status...$(NC)"
	@if ! command -v ngrok &> /dev/null; then \
		echo -e "$(RED)ngrok is not installed!$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-setup' to install it$(NC)"; \
		exit 1; \
	fi
	@echo -e "$(CYAN)ngrok version:$(NC)"
	@ngrok version
	@echo -e ""
	@if curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -q "tunnels"; then \
		echo -e "$(GREEN)ngrok is running$(NC)"; \
		echo -e "$(YELLOW)============================================================$(NC)"; \
		URL=$$(curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['tunnels'][0]['public_url'] if data['tunnels'] else 'No URL found')"); \
		echo -e "$(GREEN)Public URL:$(NC)"; \
		echo -e "$(CYAN)   $$URL$(NC)"; \
		echo -e "$(YELLOW)============================================================$(NC)"; \
		echo -e "$(BLUE)Local endpoint: http://localhost:$(APP_PORT)$(NC)"; \
		echo -e "$(BLUE)Web interface: http://localhost:4040$(NC)"; \
		echo -e ""; \
		echo -e "$(CYAN)Tunnel details:$(NC)"; \
		curl -s http://localhost:4040/api/tunnels | python3 -c "import sys, json; data = json.load(sys.stdin); t = data['tunnels'][0] if data['tunnels'] else {}; print(f\"  Protocol: {t.get('proto', 'N/A')}\"); print(f\"  Region: {t.get('region', 'N/A')}\")"; \
	else \
		echo -e "$(RED)ngrok is not running$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok-background' to start a tunnel$(NC)"; \
	fi

## Stop all ngrok tunnels
ngrok-stop:
	@echo -e "$(RED)Stopping all ngrok tunnels...$(NC)"
	@if pgrep -x "ngrok" > /dev/null; then \
		killall ngrok 2>/dev/null || true; \
		echo -e "$(GREEN)All ngrok tunnels stopped$(NC)"; \
	else \
		echo -e "$(YELLOW)No active ngrok processes found$(NC)"; \
	fi

## Open ngrok web interface
ngrok-logs:
	@echo -e "$(BLUE)Opening ngrok web interface...$(NC)"
	@if curl -s http://localhost:4040 2>/dev/null | grep -q "ngrok"; then \
		echo -e "$(GREEN)Web interface available at: http://localhost:4040$(NC)"; \
		echo -e "$(CYAN)Opening in browser...$(NC)"; \
		if command -v open > /dev/null; then \
			open http://localhost:4040; \
		elif command -v xdg-open > /dev/null; then \
			xdg-open http://localhost:4040; \
		else \
			echo -e "$(YELLOW)Please open http://localhost:4040 in your browser$(NC)"; \
		fi; \
	else \
		echo -e "$(RED)No active ngrok tunnel found$(NC)"; \
		echo -e "$(YELLOW)Run 'make ngrok' first to start a tunnel$(NC)"; \
	fi

# ============================================================================
# HELP
# ============================================================================

## Display help
help:
	@echo -e "$(BLUE)Lunch Bot Makefile Commands$(NC)"
	@echo -e "$(YELLOW)============================================================$(NC)"
	@echo -e ""
	@echo -e "$(GREEN)Ngrok Commands:$(NC)"
	@echo -e "  $(BLUE)make ngrok$(NC)            - Start ngrok tunnel on port $(APP_PORT) (foreground)"
	@echo -e "  $(BLUE)make ngrok-background$(NC) - Start ngrok in background and show URL"
	@echo -e "  $(BLUE)make ngrok-url$(NC)        - Get current tunnel URL"
	@echo -e "  $(BLUE)make ngrok-setup$(NC)      - Install and configure ngrok"
	@echo -e "  $(BLUE)make ngrok-status$(NC)     - Check ngrok status and active tunnels"
	@echo -e "  $(BLUE)make ngrok-stop$(NC)       - Stop all ngrok tunnels"
	@echo -e "  $(BLUE)make ngrok-logs$(NC)       - Open ngrok web interface (localhost:4040)"
	@echo -e ""
	@echo -e "$(YELLOW)Tips:$(NC)"
	@echo -e "  - Run $(BLUE)composer dev$(NC) to start the Laravel dev server first"
	@echo -e "  - Use $(BLUE)make ngrok-background$(NC) for Slack webhook development"
	@echo -e "  - The public URL is needed for Slack Events API and Interactivity endpoints"
	@echo -e "  - Update your Slack app URLs when the ngrok URL changes"
	@echo -e ""
	@echo -e "$(CYAN)Slack App Configuration:$(NC)"
	@echo -e "  - Events API URL: <ngrok-url>/api/slack/events"
	@echo -e "  - Interactivity URL: <ngrok-url>/api/slack/interactivity"
	@echo -e "$(YELLOW)============================================================$(NC)"
